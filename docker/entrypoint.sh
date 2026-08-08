#!/bin/sh
set -e

# ============================================================
# Content Audit Platform - 容器启动入口脚本
# ============================================================
# 设计原则:
#   - 只有 APP_ROLE=app (默认) 的容器会等待 MySQL/Redis + 生成缓存
#   - APP_ROLE=horizon / reverb / scheduler 的容器跳过等待/缓存，直接 exec CMD
#   - 这避免了 4 个容器共用同一镜像时互相等待、文件锁冲突的问题
# ============================================================

APP_ROLE="${APP_ROLE:-app}"

if [ "$APP_ROLE" = "app" ]; then
  echo "[entrypoint:app] 等待 MySQL 就绪..."
  if command -v mysqladmin >/dev/null 2>&1; then
    until mysqladmin ping -h mysql -u root -p"$DB_PASSWORD" --silent 2>/dev/null; do
      echo "  MySQL 未就绪, 等待..."
      sleep 2
    done
    echo "[entrypoint] MySQL 已就绪."
  else
    echo "  [!] mysqladmin 不存在 (mariadb-client 未安装?)，跳过 MySQL 等待"
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

  # 自动生成 APP_KEY (如果未设置)
  if ! grep -q "APP_KEY=base64:" /var/www/.env 2>/dev/null; then
    echo "[entrypoint] APP_KEY 未设置, 自动生成..."
    cd /var/www && php artisan key:generate --force
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
