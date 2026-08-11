#!/bin/sh
set -e

# ============================================================
# Content Audit Platform - 容器启动入口脚本
# ============================================================
# 设计原则:
#   - 只有 APP_ROLE=app (默认) 的容器会等待 MySQL/Redis + 生成缓存
#   - APP_ROLE=horizon / reverb / scheduler 的容器跳过等待/缓存，直接 exec CMD
#   - 这避免了 4 个容器共用同一镜像时互相等待、文件锁冲突的问题
#   - 所有角色都必须确保 .env 文件存在且 APP_KEY 已生成（Laravel 硬依赖）
# ============================================================

APP_ROLE="${APP_ROLE:-app}"

# ===================== 所有角色共用：确保 .env 存在 + APP_KEY 已生成 =====================
# Laravel 启动必须有 .env 文件，否则 app() 容器返回 null，导致 "Call to a member function make() on null"
if [ ! -f /var/www/.env ]; then
  if [ -f /var/www/.env.production ]; then
    echo "[entrypoint:${APP_ROLE}] .env 不存在, 从 .env.production 创建..."
    cp /var/www/.env.production /var/www/.env
  elif [ -f /var/www/.env.example ]; then
    echo "[entrypoint:${APP_ROLE}] .env 不存在, 从 .env.example 创建..."
    cp /var/www/.env.example /var/www/.env
  else
    echo "[entrypoint:${APP_ROLE}] 警告: 没有 .env 模板文件，Laravel 可能无法启动"
  fi
fi

if ! grep -q "^APP_KEY=base64:" /var/www/.env 2>/dev/null; then
  echo "[entrypoint:${APP_ROLE}] APP_KEY 未设置, 自动生成..."

  # 方法 1: 尝试 artisan key:generate（正常路径）
  cd /var/www && php artisan key:generate --force --no-interaction 2>/dev/null || true

  # 方法 2: fallback - 如果 artisan 因 OPcache CLI 冲突等原因失败，直接用 PHP 生成 APP_KEY 并写入 .env
  if ! grep -q "^APP_KEY=base64:" /var/www/.env 2>/dev/null; then
    echo "[entrypoint:${APP_ROLE}] artisan key:generate 未能写入 APP_KEY，使用 fallback 直接生成..."
    FALLBACK_KEY=$(php -r 'echo "base64:" . base64_encode(random_bytes(32));')
    if [ -n "$FALLBACK_KEY" ]; then
      # 如果 .env 中已有空的 APP_KEY= 行，则替换；否则追加
      if grep -q "^APP_KEY=" /var/www/.env 2>/dev/null; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${FALLBACK_KEY}|" /var/www/.env
      else
        echo "APP_KEY=${FALLBACK_KEY}" >> /var/www/.env
      fi
      echo "[entrypoint:${APP_ROLE}] APP_KEY 已通过 fallback 写入: ${FALLBACK_KEY}"
    else
      echo "[entrypoint:${APP_ROLE}] 严重错误: 无法生成 APP_KEY！Laravel 将无法启动"
    fi
  fi

  # 确保 .env 权限正确（horizon/reverb/scheduler 以 www-data 身份运行）
  chown www-data:www-data /var/www/.env 2>/dev/null || true
fi

# ===================== app 角色专属：等待基础设施就绪 + 缓存优化 =====================
if [ "$APP_ROLE" = "app" ]; then
  echo "[entrypoint:app] 等待 MySQL 就绪..."
  # 使用 PHP PDO 检测 MySQL 连接。
  # 为什么不用 mysqladmin？MySQL 8.0 默认拒绝 root 远程登录，即使用 root 密码也无法从 app 容器
  # 通过 hostname "mysql" 连接；但业务用户（DB_USERNAME）在初始化时被授予了远程访问权限。
  # PHP 镜像已经内置 pdo_mysql 扩展，无需额外安装客户端工具。
  until php -r '
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: "3306";
    $db   = getenv("DB_DATABASE") ?: "content_audit";
    $user = getenv("DB_USERNAME") ?: "audit_user";
    $pass = getenv("DB_PASSWORD") ?: "audit_secret";
    try {
      $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      ]);
      $pdo->query("SELECT 1");
      exit(0);
    } catch (Exception $e) {
      exit(1);
    }
  ' 2>/dev/null; do
    echo "  MySQL 未就绪, 等待..."
    sleep 2
  done
  echo "[entrypoint] MySQL 已就绪."

  echo "[entrypoint:app] 等待 Redis 就绪..."
  if command -v redis-cli >/dev/null 2>&1; then
    until redis-cli -h redis ping 2>/dev/null | grep -q PONG; do
      echo "  Redis 未就绪, 等待..."
      sleep 2
    done
    echo "[entrypoint] Redis 已就绪."
  else
    echo "  [!] redis-cli 不存在, 跳过 Redis 等待"
  fi

  # 生产环境缓存优化（只在 app 角色里做，避免 4 容器并发跑 config:cache 文件锁竞争）
  if [ "$APP_ENV" = "production" ]; then
    echo "[entrypoint] 生产环境优化缓存..."

    # 确保 bootstrap/cache 和 storage 目录可写（防止 "Call to a member function make() on null"）
    chown -R www-data:www-data /var/www/bootstrap/cache /var/www/storage 2>/dev/null || true
    chmod -R ug+rwX /var/www/bootstrap/cache /var/www/storage 2>/dev/null || true

    php artisan config:cache || echo "[entrypoint:warn] config:cache 失败，继续启动"
    php artisan route:cache  || echo "[entrypoint:warn] route:cache 失败，继续启动"
    php artisan view:cache   || echo "[entrypoint:warn] view:cache 失败，继续启动"
    php artisan event:cache  2>/dev/null || true
  fi

  echo "[entrypoint] 启动 Supervisor..."
else
  echo "[entrypoint:${APP_ROLE}] 跳过 MySQL/Redis 等待和缓存生成，直接启动命令..."
fi

exec "$@"
