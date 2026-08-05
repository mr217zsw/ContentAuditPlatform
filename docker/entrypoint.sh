#!/bin/sh
set -e

# ============================================================
# Content Audit Platform - 容器启动入口脚本
# ============================================================

echo "[entrypoint] 等待 MySQL 就绪..."
until mysqladmin ping -h mysql -u root -proot_secret --silent 2>/dev/null; do
  echo "  MySQL 未就绪, 等待..."
  sleep 2
done
echo "[entrypoint] MySQL 已就绪."

echo "[entrypoint] 等待 Redis 就绪..."
until redis-cli -h redis ping 2>/dev/null | grep -q PONG; do
  echo "  Redis 未就绪, 等待..."
  sleep 2
done
echo "[entrypoint] Redis 已就绪."

# 自动生成 APP_KEY (如果未设置)
if ! grep -q "APP_KEY=base64:" /var/www/.env 2>/dev/null; then
  echo "[entrypoint] APP_KEY 未设置, 自动生成..."
  cd /var/www && php artisan key:generate --force
fi

# 生产环境缓存优化
if [ "$APP_ENV" = "production" ]; then
  echo "[entrypoint] 生产环境优化缓存..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
fi

echo "[entrypoint] 启动 Supervisor..."
exec "$@"
