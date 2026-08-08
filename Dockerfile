# ============================================================
# Content Audit Platform - 多阶段构建 Dockerfile
# 阶段 1: 构建前端 JS/CSS 资源
# 阶段 2: 安装 PHP 依赖 (Composer)
# 阶段 3: 生产运行镜像 (PHP-FPM + Nginx + Supervisor)
# ============================================================

# ===================== 阶段 1: Node 前端构建 =====================
FROM node:20-alpine AS frontend

WORKDIR /build

# 配置国内镜像源（解决服务器拉取依赖卡死的问题）
# ESBUILD_BINARY_HOST 通过 ENV 直接传给 esbuild 安装脚本（npm 10+ 已不支持 npm config 方式）
ENV ESBUILD_BINARY_HOST=https://npmmirror.com/mirrors/esbuild
RUN npm config set registry https://registry.npmmirror.com

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund --prefer-offline

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN npm run build

# ===================== 阶段 2: Composer 依赖安装 =====================
FROM composer:2 AS vendor

WORKDIR /build

# 配置国内镜像源（解决服务器连不上 packagist.org 的问题）
RUN composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/

# 关闭 Composer 2.8+ 的安全阻断机制（policy.advisories.block 默认 true 会拒绝安装有 PKSA 公告的包）
RUN composer config -g policy.advisories.block false

COPY composer.json composer.lock* ./

# composer.lock 缺失时自动 fallback 到 update
# --no-audit: 跳过安全审计输出
# --ignore-platform-req=php: 允许 composer 用比 lock 文件要求的 PHP 版本更低的 PHP（解决 laravel/framework ^11.0 引入 PHP 8.4 要求的问题）
# --ignore-platform-req=ext-pcntl: Composer 镜像无 pcntl，但生产 PHP 镜像有
RUN if [ -f composer.lock ]; then \
        composer install \
            --no-dev \
            --no-interaction \
            --no-plugins \
            --no-scripts \
            --prefer-dist \
            --optimize-autoloader \
            --no-progress \
            --no-audit \
            --ignore-platform-req=php \
            --ignore-platform-req=ext-pcntl; \
    else \
        echo "[!] composer.lock 不存在，执行 composer update 生成依赖..."; \
        composer update \
            --no-dev \
            --no-interaction \
            --no-plugins \
            --no-scripts \
            --prefer-dist \
            --optimize-autoloader \
            --no-progress \
            --no-audit \
            --ignore-platform-req=php \
            --ignore-platform-req=ext-pcntl; \
    fi

# ===================== 阶段 3: 生产运行镜像 =====================
FROM php:8.2-fpm-alpine AS production

# ---- 系统依赖 ----
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    curl \
    oniguruma-dev \
    libxml2-dev \
    linux-headers \
    # 性能监控
    fcgi \
    # 数据库/缓存客户端（entrypoint.sh 用 mysqladmin/redis-cli 等待就绪）
    # Alpine 上 mysql-client 是 dummy 包，没有 mysqladmin 二进制，真正的工具在 mariadb-client
    mariadb-client \
    redis \
    # 安全更新
    tzdata \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone

# ---- PHP 扩展 ----
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml \
        opcache

# ---- PHP Opcache 生产优化 ----
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ---- PHP-FPM 生产优化 ----
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# ---- 工作目录 ----
WORKDIR /var/www

# ---- 复制源码 (不含 vendor 和 public/build) ----
COPY . .

# ---- 从构建阶段复制 vendor ----
COPY --from=vendor /build/vendor/ ./vendor/

# ---- 从构建阶段复制前端构建产物 ----
COPY --from=frontend /build/public/build/ ./public/build/

# ---- 权限设置 ----
RUN mkdir -p /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ---- Nginx 配置 ----
RUN mkdir -p /etc/nginx/http.d
COPY nginx/default.conf /etc/nginx/http.d/default.conf

# ---- Supervisor 配置 ----
RUN mkdir -p /etc/supervisor/conf.d /var/log/supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ---- 暴露端口 ----
EXPOSE 80

# ---- 启动脚本 ----
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
