#!/bin/sh
# 健康检查：确认 HTTP 服务已启动且可响应

response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/health 2>/dev/null || echo "000")

if [ "$response" = "200" ]; then
    exit 0
fi

exit 1
