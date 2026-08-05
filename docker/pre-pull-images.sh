#!/bin/bash
# ============================================================
# Content Audit Platform - 镜像预下载脚本
# 在服务器部署前执行: bash docker/pre-pull-images.sh
# 预拉取所有镜像, 避免首次 docker compose up 时等待
# ============================================================

set -e

IMAGES=(
  # ---- 基础服务 ----
  "php:8.2-fpm-alpine"
  "composer:2"
  "node:20-alpine"
  "mysql:8.0"
  "redis:7-alpine"

  # ---- 监控栈 ----
  "prom/prometheus:latest"
  "grafana/grafana:latest"
  "prom/node-exporter:latest"
  "oliver006/redis_exporter:latest"
  "prom/mysqld-exporter:latest"
)

echo "========================================"
echo "  预拉取 Docker 镜像"
echo "========================================"
echo ""

TOTAL=${#IMAGES[@]}
CURRENT=0

for IMAGE in "${IMAGES[@]}"; do
  CURRENT=$((CURRENT + 1))
  echo "[$CURRENT/$TOTAL] 拉取: $IMAGE"
  docker pull "$IMAGE"
  echo ""
done

echo "========================================"
echo "  全部镜像拉取完成! ($TOTAL 个)"
echo ""
echo "  下一步:"
echo "    1. cp .env.production.example .env.production"
echo "    2. 编辑 .env.production 填入真实密钥"
echo "    3. docker compose up -d"
echo "========================================"
