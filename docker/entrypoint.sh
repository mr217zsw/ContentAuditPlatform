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
  if command -v mysqladmin >/dev/null 2>&1; then
    # 先用 root 尝试（MySQL 容器刚初始化时业务用户可能还不存在）
    until mysqladmin ping -h mysql -uroot -p"$DB_PASSWORD" --silent 2>/dev/null; do
      echo "  MySQL 未就绪, 等待..."
      sleep 2
    done
    echo "[entrypoint] MySQL 已就绪."
  elif command -v mysql >/dev/null 2>&1; then
    # fallback: 没有 mysqladmin 时用 mysql 客户端检测
    until mysql -h mysql -uroot -p"$DB_PASSWORD" -e "SELECT 1" --silent 2>/dev/null; do
      echo "  MySQL 未就绪, 等待..."
      sleep 2
    done
    echo "[entrypoint] MySQL 已就绪."
  else
    echo "  [!] mysql/mysqladmin 均不存在，跳过 MySQL 等待"
  fi

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
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
  fi

  echo "[entrypoint] 启动 Supervisor..."
else
  echo "[entrypoint:${APP_ROLE}] 跳过 MySQL/Redis 等待和缓存生成，直接启动命令..."
fi

exec "$@"
