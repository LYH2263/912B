<template>
  <div class="ticket-detail page-shell" v-loading="loading">
    <div class="detail-header" v-if="ticket">
      <div class="header-left">
        <el-button link @click="goBack">
          <el-icon><ArrowLeft /></el-icon>
          返回看板
        </el-button>
        <div class="title-row">
          <h2 class="page-title">
            <span class="ticket-no-tag">{{ ticket.ticket_no }}</span>
            {{ ticket.title }}
          </h2>
          <div class="meta-row">
            <el-tag :type="getStatusTag(ticket.status)" effect="dark">
              {{ ticket.status_label }}
            </el-tag>
            <el-tag :type="getPriorityTag(ticket.priority)" effect="plain">
              优先级: {{ ticket.priority_label }}
            </el-tag>
            <el-tag :type="getCategoryTag(ticket.category)" effect="plain">
              {{ ticket.category_label }}
            </el-tag>
            <span class="meta-text">
              录入时间: {{ formatDateTime(ticket.created_at) }}
            </span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <div class="action-group" v-if="ticket.status === 'pending'">
          <el-button type="primary" round @click="updateStatus('processing')">开始处理</el-button>
          <el-button type="danger" round plain @click="updateStatus('closed')">关闭工单</el-button>
        </div>
        <div class="action-group" v-else-if="ticket.status === 'processing'">
          <el-button type="success" round @click="updateStatus('resolved')">标记已解决</el-button>
          <el-button type="warning" round plain @click="updateStatus('pending')">退回待处理</el-button>
          <el-button type="danger" round plain @click="updateStatus('closed')">关闭工单</el-button>
        </div>
        <div class="action-group" v-else-if="ticket.status === 'resolved'">
          <el-button type="warning" round @click="updateStatus('processing')">重新打开处理</el-button>
          <el-button type="info" round plain @click="updateStatus('closed')">关闭工单</el-button>
        </div>
        <div class="action-group" v-else-if="ticket.status === 'closed'">
          <el-tag type="info" effect="dark">工单已关闭</el-tag>
        </div>
      </div>
    </div>

    <div class="detail-content" v-if="ticket">
      <el-row :gutter="20">
        <el-col :span="17">
          <el-card class="info-card">
            <template #header>
              <div class="card-header">
                <el-icon class="header-icon"><Document /></el-icon>
                <span>问题描述</span>
              </div>
            </template>
            <div class="description-text">{{ ticket.description }}</div>
          </el-card>

          <el-card class="timeline-card">
            <template #header>
              <div class="card-header">
                <el-icon class="header-icon"><ChatLineSquare /></el-icon>
                <span>处理时间线</span>
                <el-tag size="small" effect="light">{{ comments.length }} 条记录</el-tag>
              </div>
            </template>

            <el-timeline class="comments-timeline">
              <el-timeline-item
                v-for="(item, idx) in timelineEvents"
                :key="idx"
                :timestamp="formatDateTime(item.created_at)"
                placement="top"
                :type="item.type"
                :size="item.size"
                :icon="item.icon ? item.iconComp : undefined"
                :hollow="item.hollow"
              >
                <div class="timeline-card-item" :class="{ internal: item.is_internal }">
                  <div class="timeline-header">
                    <div class="timeline-user">
                      <el-avatar :size="28" class="user-avatar">
                        {{ (item.user_name || '?').charAt(0).toUpperCase() }}
                      </el-avatar>
                      <span class="user-name">{{ item.user_name || '系统' }}</span>
                      <el-tag v-if="item.is_internal" size="small" type="warning" effect="dark">内部</el-tag>
                      <el-tag v-if="item.is_action" size="small" type="info" effect="plain">操作记录</el-tag>
                    </div>
                  </div>
                  <div class="timeline-content">{{ item.content }}</div>
                </div>
              </el-timeline-item>
              <el-timeline-item
                :timestamp="formatDateTime(ticket.created_at)"
                placement="top"
                type="primary"
                size="large"
              >
                <div class="timeline-card-item created-item">
                  <el-tag type="primary" effect="dark">工单创建</el-tag>
                  <span class="creator-text">
                    由 {{ ticket.creator?.name || ticket.creator?.email || '管理员' }} 录入工单
                  </span>
                </div>
              </el-timeline-item>
            </el-timeline>

            <div class="comment-input-area" v-if="ticket.status !== 'closed'">
              <el-divider />
              <div class="comment-header">
                <span class="comment-label">添加评论 / 备注</span>
                <el-checkbox v-model="isInternalComment">
                  <el-icon><Lock /></el-icon>
                  标记为内部评论
                </el-checkbox>
              </div>
              <el-input
                v-model="newComment"
                type="textarea"
                :rows="3"
                placeholder="输入评论内容，记录处理进展..."
                maxlength="1000"
                show-word-limit
              />
              <div class="comment-actions">
                <el-button type="primary" :disabled="!newComment.trim()" :loading="commenting" @click="submitComment">
                  <el-icon><Promotion /></el-icon>
                  发表评论
                </el-button>
              </div>
            </div>
          </el-card>
        </el-col>

        <el-col :span="7">
          <el-card class="side-card">
            <template #header>
              <div class="card-header">
                <el-icon class="header-icon"><UserFilled /></el-icon>
                <span>处理人</span>
              </div>
            </template>
            <div class="assignee-section">
              <div class="assignee-display" v-if="ticket.assignee">
                <el-avatar :size="56" class="big-avatar">
                  {{ (ticket.assignee.name || ticket.assignee.email || '?').charAt(0).toUpperCase() }}
                </el-avatar>
                <div class="assignee-info">
                  <div class="assignee-name">{{ ticket.assignee.name || ticket.assignee.email }}</div>
                  <div class="assignee-email">{{ ticket.assignee.email }}</div>
                </div>
              </div>
              <div class="assignee-display unassigned" v-else>
                <el-avatar :size="56" class="big-avatar empty">
                  <el-icon :size="28"><User /></el-icon>
                </el-avatar>
                <div class="assignee-info">
                  <div class="assignee-name text-muted">暂未指派</div>
                  <div class="assignee-email text-muted">请选择处理人跟进</div>
                </div>
              </div>
              <el-select
                v-model="selectedAssignee"
                placeholder="选择/更改处理人"
                style="width: 100%; margin-top: 16px"
                clearable
                filterable
              >
                <el-option
                  v-for="user in assigneeList"
                  :key="user.id"
                  :label="user.name || user.email"
                  :value="user.id"
                />
              </el-select>
              <el-button
                type="primary"
                style="width: 100%; margin-top: 10px"
                :loading="assigning"
                :disabled="!hasAssigneeChanged"
                @click="submitAssign"
              >
                <el-icon><Pointer /></el-icon>
                确认指派
              </el-button>
            </div>
          </el-card>

          <el-card class="side-card" style="margin-top: 16px">
            <template #header>
              <div class="card-header">
                <el-icon class="header-icon"><InfoFilled /></el-icon>
                <span>工单信息</span>
              </div>
            </template>
            <div class="info-list">
              <div class="info-item">
                <span class="info-label">工单号</span>
                <span class="info-value mono">{{ ticket.ticket_no }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">录入人</span>
                <span class="info-value">
                  {{ ticket.creator?.name || ticket.creator?.email || '-' }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">处理人</span>
                <span class="info-value">
                  {{ ticket.assignee?.name || ticket.assignee?.email || '未指派' }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">创建时间</span>
                <span class="info-value">{{ formatDateTime(ticket.created_at) }}</span>
              </div>
              <div class="info-item" v-if="ticket.resolved_at">
                <span class="info-label">解决时间</span>
                <span class="info-value success">{{ formatDateTime(ticket.resolved_at) }}</span>
              </div>
              <div class="info-item" v-if="ticket.closed_at">
                <span class="info-label">关闭时间</span>
                <span class="info-value text-muted">{{ formatDateTime(ticket.closed_at) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">更新时间</span>
                <span class="info-value">{{ formatDateTime(ticket.updated_at) }}</span>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <el-dialog v-model="statusDialogVisible" title="状态变更备注" width="500px">
      <p class="dialog-tip">将工单状态变更为：<strong>{{ statusTextMap[targetStatus] }}</strong></p>
      <el-input
        v-model="statusComment"
        type="textarea"
        :rows="3"
        placeholder="可选：输入状态变更原因或处理说明..."
      />
      <template #footer>
        <el-button @click="statusDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="statusUpdating" @click="confirmStatusUpdate">确认变更</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowLeft, Document, ChatLineSquare, User, UserFilled, Pointer,
  Promotion, Lock, InfoFilled, CircleCheck, WarningFilled
} from '@element-plus/icons-vue'
import { ticketApi } from '@/api/modules/ticket'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const commenting = ref(false)
const assigning = ref(false)
const statusUpdating = ref(false)
const statusDialogVisible = ref(false)
const targetStatus = ref('')
const statusComment = ref('')

const ticket = ref(null)
const comments = ref([])
const assigneeList = ref([])
const newComment = ref('')
const isInternalComment = ref(false)
const selectedAssignee = ref(null)
const originalAssigneeId = ref(null)

const statusTextMap = {
  pending: '待处理',
  processing: '处理中',
  resolved: '已解决',
  closed: '已关闭',
}

const hasAssigneeChanged = computed(() => {
  const orig = originalAssigneeId.value ?? null
  const curr = selectedAssignee.value ?? null
  return orig !== curr
})

const timelineEvents = computed(() => {
  const list = [...comments.value]
    .map((c) => {
      const isAction = c.content && (c.content.startsWith('[') || c.content.includes(']'))
      return {
        ...c,
        user_name: c.user?.name || c.user?.email || '系统',
        is_action: isAction,
        type: c.is_internal ? 'warning' : (isAction ? 'info' : 'primary'),
        size: 'normal',
        hollow: c.is_internal,
        iconComp: isAction ? InfoFilled : ChatLineSquare,
      }
    })
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  return list
})

const getStatusTag = (status) => {
  const map = { pending: 'warning', processing: 'primary', resolved: 'success', closed: 'info' }
  return map[status] || 'info'
}

const getPriorityTag = (priority) => {
  const map = { high: 'danger', medium: 'warning', low: 'info' }
  return map[priority] || 'info'
}

const getCategoryTag = (category) => {
  const map = { logistics: '', quality: 'danger', refund: 'warning' }
  return map[category] || ''
}

const formatDateTime = (time) => {
  if (!time) return '-'
  const d = new Date(time)
  const pad = (n) => n.toString().padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const fetchTicket = async () => {
  loading.value = true
  try {
    const res = await ticketApi.getTicket(route.params.id)
    ticket.value = res.data
    comments.value = res.data?.comments || []
    originalAssigneeId.value = res.data?.assigned_to ?? null
    selectedAssignee.value = res.data?.assigned_to ?? null
  } catch (e) {
    ElMessage.error('获取工单详情失败')
  } finally {
    loading.value = false
  }
}

const fetchAssignees = async () => {
  try {
    const res = await ticketApi.getAssignees()
    assigneeList.value = res.data || []
  } catch (e) {
    console.error('获取处理人列表失败', e)
  }
}

const updateStatus = (status) => {
  targetStatus.value = status
  statusComment.value = ''
  statusDialogVisible.value = true
}

const confirmStatusUpdate = async () => {
  statusUpdating.value = true
  try {
    await ticketApi.updateTicketStatus(ticket.value.id, targetStatus.value, statusComment.value || undefined)
    ElMessage.success('状态更新成功')
    statusDialogVisible.value = false
    fetchTicket()
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '状态更新失败')
  } finally {
    statusUpdating.value = false
  }
}

const submitComment = async () => {
  if (!newComment.value.trim()) return
  commenting.value = true
  try {
    await ticketApi.addComment(ticket.value.id, newComment.value.trim(), isInternalComment.value)
    ElMessage.success('评论发布成功')
    newComment.value = ''
    isInternalComment.value = false
    fetchTicket()
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '评论发布失败')
  } finally {
    commenting.value = false
  }
}

const submitAssign = async () => {
  assigning.value = true
  try {
    await ticketApi.assignTicket(ticket.value.id, selectedAssignee.value || null)
    ElMessage.success('处理人指派成功')
    originalAssigneeId.value = selectedAssignee.value ?? null
    fetchTicket()
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '指派失败')
  } finally {
    assigning.value = false
  }
}

const goBack = () => {
  router.push('/tickets')
}

onMounted(() => {
  fetchTicket()
  fetchAssignees()
})
</script>

<style scoped>
.ticket-detail {
  padding: 24px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 22px 26px;
  background: linear-gradient(135deg, #ffffff 0%, #f5f7ff 100%);
  border-radius: 20px;
  border: 1px solid rgba(99, 102, 241, 0.15);
  box-shadow: 0 12px 32px rgba(99, 102, 241, 0.12);
  margin-bottom: 20px;
}

.header-left {
  flex: 1;
}

.title-row {
  margin-top: 10px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2933;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.ticket-no-tag {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border-radius: 999px;
  letter-spacing: 0.04em;
}

.meta-row {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.meta-text {
  font-size: 12px;
  color: #9ca3af;
}

.action-group {
  display: flex;
  gap: 8px;
}

.info-card,
.timeline-card,
.side-card {
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 15px;
  color: #1f2933;
}

.header-icon {
  color: #6366f1;
}

.description-text {
  font-size: 14px;
  line-height: 1.75;
  color: #374151;
  white-space: pre-wrap;
  padding: 6px 0;
}

.comments-timeline {
  padding: 10px 6px;
}

.timeline-card-item {
  padding: 14px 16px;
  background: #f9fafb;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
}

.timeline-card-item.internal {
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border-color: #f59e0b40;
}

.timeline-card-item.created-item {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-color: #3b82f640;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
}

.creator-text {
  font-size: 13px;
  color: #4b5563;
}

.timeline-header {
  margin-bottom: 8px;
}

.timeline-user {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-avatar {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  border: none;
}

.user-name {
  font-size: 13px;
  font-weight: 600;
  color: #1f2933;
}

.timeline-content {
  font-size: 13px;
  line-height: 1.7;
  color: #374151;
  white-space: pre-wrap;
}

.comment-input-area {
  margin-top: 4px;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.comment-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.comment-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

.assignee-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 0;
}

.assignee-display {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
}

.assignee-display.unassigned .big-avatar {
  background: #f3f4f6;
  color: #9ca3af;
}

.big-avatar {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  font-size: 20px;
  font-weight: 600;
  border: none;
}

.big-avatar.empty {
  background: #f3f4f6 !important;
  color: #9ca3af;
}

.assignee-info {
  flex: 1;
}

.assignee-name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.assignee-email {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.text-muted {
  color: #9ca3af !important;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  padding: 8px 0;
  border-bottom: 1px dashed #f1f5f9;
}

.info-item:last-child {
  border-bottom: none;
}

.info-label {
  color: #6b7280;
}

.info-value {
  color: #1f2933;
  font-weight: 500;
  text-align: right;
  max-width: 60%;
  word-break: break-all;
}

.info-value.mono {
  font-family: 'SF Mono', Monaco, Consolas, monospace;
  color: #6366f1;
}

.info-value.success {
  color: #67c23a;
}

.dialog-tip {
  font-size: 14px;
  color: #374151;
  margin-bottom: 14px;
}
</style>
