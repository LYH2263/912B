<template>
  <div class="notification-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">消息中心</span>
            <span class="card-subtitle">查看系统通知、订单发货与库存预警消息</span>
          </div>
          <div class="card-header-actions">
            <el-button type="primary" plain @click="handleMarkAllRead" :disabled="unreadCount === 0">
              全部已读
            </el-button>
          </div>
        </div>
      </template>

      <el-tabs v-model="activeTab" @tab-change="handleTabChange" class="notification-tabs">
        <el-tab-pane label="全部" name="all">
          <span class="tab-count">{{ totalCount }}</span>
        </el-tab-pane>
        <el-tab-pane label="未读" name="unread">
          <span class="tab-count">{{ unreadCount }}</span>
        </el-tab-pane>
        <el-tab-pane label="已读" name="read">
          <span class="tab-count">{{ readCount }}</span>
        </el-tab-pane>
      </el-tabs>

      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="消息类型">
          <el-select v-model="filters.type" placeholder="全部类型" clearable style="width: 160px" @change="handleSearch">
            <el-option label="订单发货" value="order_shipped" />
            <el-option label="库存预警" value="stock_warning" />
            <el-option label="系统通知" value="system" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="notification-container" v-loading="loading">
        <el-empty v-if="notifications.length === 0 && !loading" description="暂无消息" />
        <div
          v-for="item in notifications"
          :key="item.id"
          class="notification-item"
          :class="{ unread: !item.is_read }"
          @click="handleItemClick(item)"
        >
          <div class="item-left">
            <div class="item-icon" :class="getTypeClass(item.type)">
              <el-icon v-if="item.type === 'order_shipped'" size="18"><Van /></el-icon>
              <el-icon v-else-if="item.type === 'stock_warning'" size="18"><Warning /></el-icon>
              <el-icon v-else size="18"><Bell /></el-icon>
            </div>
            <div class="item-main">
              <div class="item-header">
                <span class="item-title">{{ item.title }}</span>
                <el-tag v-if="!item.is_read" type="danger" size="small" effect="light">未读</el-tag>
                <el-tag :type="getTypeTagType(item.type)" size="small" effect="plain">
                  {{ getTypeLabel(item.type) }}
                </el-tag>
              </div>
              <div class="item-content">{{ item.content }}</div>
              <div class="item-meta">
                <span class="item-time">
                  <el-icon size="12"><Clock /></el-icon>
                  {{ formatTime(item.created_at) }}
                </span>
                <el-button
                  v-if="!item.is_read"
                  type="primary"
                  link
                  size="small"
                  @click.stop="handleMarkRead(item)"
                >
                  标记已读
                </el-button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        style="margin-top: 20px; justify-content: flex-end"
      />
    </el-card>

    <el-dialog v-model="detailVisible" title="消息详情" width="600px">
      <div v-if="currentItem" class="detail-content">
        <div class="detail-header">
          <div class="detail-icon" :class="getTypeClass(currentItem.type)">
            <el-icon v-if="currentItem.type === 'order_shipped'" size="20"><Van /></el-icon>
            <el-icon v-else-if="currentItem.type === 'stock_warning'" size="20"><Warning /></el-icon>
            <el-icon v-else size="20"><Bell /></el-icon>
          </div>
          <div class="detail-title-wrap">
            <h3 class="detail-title">{{ currentItem.title }}</h3>
            <div class="detail-meta">
              <el-tag :type="getTypeTagType(currentItem.type)" size="small">
                {{ getTypeLabel(currentItem.type) }}
              </el-tag>
              <span class="detail-time">
                <el-icon size="12"><Clock /></el-icon>
                {{ formatTime(currentItem.created_at) }}
              </span>
              <el-tag v-if="currentItem.is_read" type="info" size="small">已读</el-tag>
              <el-tag v-else type="danger" size="small">未读</el-tag>
            </div>
          </div>
        </div>
        <el-divider />
        <div class="detail-body">
          <div class="detail-text">
            <pre>{{ currentItem.content }}</pre>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Bell, Van, Warning, Clock } from '@element-plus/icons-vue'
import dayjs from 'dayjs'
import { notificationApi } from '@/api/modules/notification'

const route = useRoute()
const router = useRouter()

const notifications = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const unreadCount = ref(0)
const readCount = ref(0)
const totalCount = ref(0)

const detailVisible = ref(false)
const currentItem = ref(null)

const activeTab = ref(route.query.tab || 'all')

const filters = reactive({
  type: route.query.type || '',
})

const getTypeClass = (type) => {
  const map = {
    order_shipped: 'type-shipped',
    stock_warning: 'type-warning',
    system: 'type-system',
  }
  return map[type] || 'type-system'
}

const getTypeLabel = (type) => {
  const map = {
    order_shipped: '订单发货',
    stock_warning: '库存预警',
    system: '系统通知',
  }
  return map[type] || type
}

const getTypeTagType = (type) => {
  const map = {
    order_shipped: 'success',
    stock_warning: 'warning',
    system: 'primary',
  }
  return map[type] || 'info'
}

const formatTime = (time) => {
  return dayjs(time).format('YYYY-MM-DD HH:mm:ss')
}

const fetchNotifications = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
    }

    if (activeTab.value === 'unread') {
      params.is_read = false
    } else if (activeTab.value === 'read') {
      params.is_read = true
    }

    if (filters.type) {
      params.type = filters.type
    }

    const res = await notificationApi.getNotifications(params)
    notifications.value = res.data || []
    total.value = res.meta?.total || 0

    fetchCounts()
  } catch (e) {
    ElMessage.error('获取消息列表失败')
  } finally {
    loading.value = false
  }
}

const fetchCounts = async () => {
  try {
    const [allRes, unreadRes, readRes] = await Promise.all([
      notificationApi.getNotifications({ per_page: 1, ...(filters.type ? { type: filters.type } : {}) }),
      notificationApi.getNotifications({ per_page: 1, is_read: false, ...(filters.type ? { type: filters.type } : {}) }),
      notificationApi.getNotifications({ per_page: 1, is_read: true, ...(filters.type ? { type: filters.type } : {}) }),
    ])
    totalCount.value = allRes.meta?.total || 0
    unreadCount.value = unreadRes.meta?.total || 0
    readCount.value = readRes.meta?.total || 0
  } catch (e) {
    console.error('获取统计数失败', e)
  }
}

const handleTabChange = () => {
  currentPage.value = 1
  fetchNotifications()
  updateRouteQuery()
}

const handleSearch = () => {
  currentPage.value = 1
  fetchNotifications()
  updateRouteQuery()
}

const handleReset = () => {
  filters.type = ''
  activeTab.value = 'all'
  currentPage.value = 1
  fetchNotifications()
  updateRouteQuery()
}

const updateRouteQuery = () => {
  const query = {}
  if (activeTab.value !== 'all') query.tab = activeTab.value
  if (filters.type) query.type = filters.type
  router.replace({ query })
}

const handleItemClick = async (item) => {
  currentItem.value = item
  detailVisible.value = true
  if (!item.is_read) {
    try {
      await notificationApi.markAsRead(item.id)
      item.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      readCount.value = readCount.value + 1
    } catch (e) {
      console.error('标记已读失败', e)
    }
  }
}

const handleMarkRead = async (item) => {
  try {
    await notificationApi.markAsRead(item.id)
    item.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    readCount.value = readCount.value + 1
    ElMessage.success('已标记为已读')
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

const handleMarkAllRead = async () => {
  try {
    await ElMessageBox.confirm('确定要将所有消息标记为已读吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
    const res = await notificationApi.markAllAsRead()
    ElMessage.success(`已将 ${res.data.marked_count} 条消息标记为已读`)
    notifications.value.forEach(item => {
      item.is_read = true
    })
    unreadCount.value = 0
    readCount.value = totalCount.value
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error('操作失败')
    }
  }
}

const handleSizeChange = () => {
  currentPage.value = 1
  fetchNotifications()
}

const handleCurrentChange = () => {
  fetchNotifications()
}

onMounted(() => {
  fetchNotifications()
})

watch(() => route.query, (q) => {
  if (q.tab && q.tab !== activeTab.value) {
    activeTab.value = q.tab
  }
  if (q.type !== undefined && q.type !== filters.type) {
    filters.type = q.type
  }
}, { immediate: false })
</script>

<style scoped>
.notification-list {
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
}

.card-subtitle {
  font-size: 12px;
  color: #6b7280;
}

.filter-form {
  margin-bottom: 16px;
}

.notification-tabs {
  margin-bottom: 12px;
}

.notification-tabs :deep(.el-tabs__item) {
  position: relative;
}

.tab-count {
  margin-left: 4px;
  font-size: 12px;
  color: #9ca3af;
  background-color: #f3f4f6;
  padding: 1px 6px;
  border-radius: 10px;
}

.notification-container {
  min-height: 300px;
}

.notification-item {
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s;
  background-color: #fff;
}

.notification-item:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
  transform: translateY(-1px);
}

.notification-item.unread {
  background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);
  border-color: #bfdbfe;
}

.item-left {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.item-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.item-icon.type-shipped {
  background: linear-gradient(135deg, #10b981, #059669);
}

.item-icon.type-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.item-icon.type-system {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
}

.item-main {
  flex: 1;
  min-width: 0;
}

.item-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.item-title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  flex: 1;
}

.item-content {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.6;
  margin-bottom: 8px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  white-space: pre-wrap;
}

.item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.item-time {
  font-size: 12px;
  color: #9ca3af;
  display: flex;
  align-items: center;
  gap: 4px;
}

.detail-content {
  padding: 4px;
}

.detail-header {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.detail-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.detail-icon.type-shipped {
  background: linear-gradient(135deg, #10b981, #059669);
}

.detail-icon.type-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.detail-icon.type-system {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
}

.detail-title-wrap {
  flex: 1;
  min-width: 0;
}

.detail-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 8px 0;
}

.detail-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.detail-time {
  font-size: 12px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 4px;
}

.detail-body {
  padding: 8px 0;
}

.detail-text pre {
  margin: 0;
  padding: 16px;
  background-color: #f9fafb;
  border-radius: 8px;
  font-family: inherit;
  font-size: 14px;
  color: #374151;
  line-height: 1.8;
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
