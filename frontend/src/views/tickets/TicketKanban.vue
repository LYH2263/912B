<template>
  <div class="ticket-kanban page-shell">
    <div class="kanban-header">
      <div>
        <h2 class="page-title">售后工单看板</h2>
        <p class="page-subtitle">按状态管理客户问题工单，高效追踪处理进度</p>
      </div>
      <div class="header-actions">
        <el-button type="primary" round @click="openCreateDialog">
          <el-icon><Plus /></el-icon>
          录入工单
        </el-button>
        <el-button round @click="fetchKanban">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-select v-model="filterCategory" placeholder="分类筛选" clearable style="width: 140px" @change="fetchKanban">
        <el-option label="物流问题" value="logistics" />
        <el-option label="质量问题" value="quality" />
        <el-option label="退款咨询" value="refund" />
      </el-select>
      <el-select v-model="filterPriority" placeholder="优先级" clearable style="width: 120px" @change="fetchKanban">
        <el-option label="高" value="high" />
        <el-option label="中" value="medium" />
        <el-option label="低" value="low" />
      </el-select>
      <el-input
        v-model="filterKeyword"
        placeholder="搜索工单号/标题"
        clearable
        style="width: 220px"
        :prefix-icon="Search"
        @keyup.enter="fetchKanban"
      />
    </div>

    <div class="kanban-columns" v-loading="loading">
      <div
        v-for="column in columns"
        :key="column.status"
        class="kanban-column"
        :class="`column-${column.status}`"
      >
        <div class="column-header">
          <div class="column-title">
            <span class="column-dot"></span>
            <span>{{ column.label }}</span>
            <el-tag size="small" :type="column.tagType" effect="light">
              {{ (filteredTickets[column.status] || []).length }}
            </el-tag>
          </div>
        </div>
        <div class="column-body">
          <div
            v-for="ticket in filteredTickets[column.status]"
            :key="ticket.id"
            class="ticket-card"
            :class="`priority-${ticket.priority}`"
            @click="goDetail(ticket.id)"
          >
            <div class="card-header">
              <span class="ticket-no">{{ ticket.ticket_no }}</span>
              <el-tag size="small" :type="getPriorityTag(ticket.priority)" effect="dark">
                {{ ticket.priority_label }}
              </el-tag>
            </div>
            <div class="card-title">{{ ticket.title }}</div>
            <div class="card-description">{{ truncateText(ticket.description, 60) }}</div>
            <div class="card-meta">
              <el-tag size="small" :type="getCategoryTag(ticket.category)" effect="plain">
                {{ ticket.category_label }}
              </el-tag>
            </div>
            <div class="card-footer">
              <div class="assignee" v-if="ticket.assignee">
                <el-avatar :size="22" class="mini-avatar">
                  {{ (ticket.assignee.name || ticket.assignee.email || '?').charAt(0).toUpperCase() }}
                </el-avatar>
                <span>{{ ticket.assignee.name || ticket.assignee.email }}</span>
              </div>
              <div class="assignee unassigned" v-else>
                <el-icon :size="14"><User /></el-icon>
                <span>未指派</span>
              </div>
              <span class="created-time">{{ formatTime(ticket.created_at) }}</span>
            </div>
            <div class="card-quick-actions" @click.stop>
              <template v-if="column.status === 'pending'">
                <el-button size="small" type="primary" link @click="quickUpdate(ticket, 'processing')">
                  开始处理
                </el-button>
                <el-button size="small" type="danger" link @click="quickUpdate(ticket, 'closed')">
                  关闭
                </el-button>
              </template>
              <template v-else-if="column.status === 'processing'">
                <el-button size="small" type="success" link @click="quickUpdate(ticket, 'resolved')">
                  标记解决
                </el-button>
                <el-button size="small" type="danger" link @click="quickUpdate(ticket, 'closed')">
                  关闭
                </el-button>
              </template>
              <template v-else-if="column.status === 'resolved'">
                <el-button size="small" type="warning" link @click="quickUpdate(ticket, 'processing')">
                  重新处理
                </el-button>
                <el-button size="small" type="info" link @click="quickUpdate(ticket, 'closed')">
                  关闭
                </el-button>
              </template>
            </div>
          </div>
          <div class="empty-column" v-if="!filteredTickets[column.status] || filteredTickets[column.status].length === 0">
            <el-empty :description="'暂无工单'" :image-size="60" />
          </div>
        </div>
      </div>
    </div>

    <el-dialog v-model="createDialogVisible" title="录入新工单" width="600px" :close-on-click-modal="false">
      <el-form :model="createForm" :rules="createRules" ref="createFormRef" label-width="100px">
        <el-form-item label="工单标题" prop="title">
          <el-input v-model="createForm.title" placeholder="请输入工单标题" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="问题分类" prop="category">
          <el-select v-model="createForm.category" placeholder="请选择分类" style="width: 100%">
            <el-option label="物流问题" value="logistics" />
            <el-option label="质量问题" value="quality" />
            <el-option label="退款咨询" value="refund" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级" prop="priority">
          <el-radio-group v-model="createForm.priority">
            <el-radio-button label="high">高</el-radio-button>
            <el-radio-button label="medium">中</el-radio-button>
            <el-radio-button label="low">低</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="指派处理人" prop="assigned_to">
          <el-select v-model="createForm.assigned_to" placeholder="请选择处理人（可选）" clearable style="width: 100%">
            <el-option
              v-for="user in assigneeList"
              :key="user.id"
              :label="user.name || user.email"
              :value="user.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="问题描述" prop="description">
          <el-input
            v-model="createForm.description"
            type="textarea"
            :rows="4"
            placeholder="请详细描述客户问题"
            maxlength="2000"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="补充备注">
          <el-input
            v-model="createForm.comment"
            type="textarea"
            :rows="2"
            placeholder="添加初始备注（可选）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitCreate">创建工单</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search, User } from '@element-plus/icons-vue'
import { ticketApi } from '@/api/modules/ticket'

const router = useRouter()

const loading = ref(false)
const ticketsByStatus = ref({
  pending: [],
  processing: [],
  resolved: [],
  closed: [],
})
const counts = ref({})
const filterCategory = ref('')
const filterPriority = ref('')
const filterKeyword = ref('')

const createDialogVisible = ref(false)
const submitting = ref(false)
const createFormRef = ref(null)
const assigneeList = ref([])

const columns = [
  { status: 'pending', label: '待处理', tagType: 'warning' },
  { status: 'processing', label: '处理中', tagType: 'primary' },
  { status: 'resolved', label: '已解决', tagType: 'success' },
  { status: 'closed', label: '已关闭', tagType: 'info' },
]

const createForm = reactive({
  title: '',
  category: '',
  priority: 'medium',
  description: '',
  assigned_to: null,
  comment: '',
})

const createRules = {
  title: [{ required: true, message: '请输入工单标题', trigger: 'blur' }],
  category: [{ required: true, message: '请选择问题分类', trigger: 'change' }],
  priority: [{ required: true, message: '请选择优先级', trigger: 'change' }],
  description: [{ required: true, message: '请输入问题描述', trigger: 'blur' }],
}

const filteredTickets = computed(() => {
  const result = {}
  for (const status of ['pending', 'processing', 'resolved', 'closed']) {
    let list = ticketsByStatus.value[status] || []
    if (filterCategory.value) {
      list = list.filter(t => t.category === filterCategory.value)
    }
    if (filterPriority.value) {
      list = list.filter(t => t.priority === filterPriority.value)
    }
    if (filterKeyword.value) {
      const kw = filterKeyword.value.toLowerCase()
      list = list.filter(t =>
        t.title.toLowerCase().includes(kw) ||
        t.ticket_no.toLowerCase().includes(kw)
      )
    }
    result[status] = list
  }
  return result
})

const getPriorityTag = (priority) => {
  const map = { high: 'danger', medium: 'warning', low: 'info' }
  return map[priority] || 'info'
}

const getCategoryTag = (category) => {
  const map = { logistics: '', quality: 'danger', refund: 'warning' }
  return map[category] || ''
}

const truncateText = (text, max) => {
  if (!text) return ''
  return text.length > max ? text.slice(0, max) + '...' : text
}

const formatTime = (time) => {
  if (!time) return ''
  const d = new Date(time)
  const pad = (n) => n.toString().padStart(2, '0')
  return `${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const fetchKanban = async () => {
  loading.value = true
  try {
    const res = await ticketApi.getTicketsKanban()
    ticketsByStatus.value = res.data || {}
    counts.value = res.counts || {}
  } catch (e) {
    ElMessage.error('获取工单看板失败')
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

const openCreateDialog = () => {
  Object.assign(createForm, {
    title: '',
    category: '',
    priority: 'medium',
    description: '',
    assigned_to: null,
    comment: '',
  })
  createFormRef.value?.resetFields()
  fetchAssignees()
  createDialogVisible.value = true
}

const submitCreate = async () => {
  await createFormRef.value?.validate()
  submitting.value = true
  try {
    const payload = { ...createForm }
    if (!payload.assigned_to) delete payload.assigned_to
    await ticketApi.createTicket(payload)
    ElMessage.success('工单创建成功')
    createDialogVisible.value = false
    fetchKanban()
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || '工单创建失败')
  } finally {
    submitting.value = false
  }
}

const quickUpdate = async (ticket, newStatus) => {
  try {
    const statusText = { pending: '待处理', processing: '处理中', resolved: '已解决', closed: '已关闭' }
    await ElMessageBox.confirm(
      `确定将工单 [${ticket.ticket_no}] 状态变更为「${statusText[newStatus]}」吗？`,
      '状态变更确认',
      { type: 'warning' }
    )
    await ticketApi.updateTicketStatus(ticket.id, newStatus)
    ElMessage.success('状态更新成功')
    fetchKanban()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e?.response?.data?.message || '状态更新失败')
    }
  }
}

const goDetail = (id) => {
  router.push(`/tickets/${id}`)
}

onMounted(() => {
  fetchKanban()
})
</script>

<style scoped>
.ticket-kanban {
  padding: 24px;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.kanban-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 16px;
}

.page-title {
  font-size: 22px;
  font-weight: 600;
  color: #1f2933;
  letter-spacing: 0.02em;
  margin: 0;
}

.page-subtitle {
  margin-top: 4px;
  font-size: 13px;
  color: #6b7280;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 18px;
  padding: 14px 18px;
  background: rgba(255, 255, 255, 0.7);
  border-radius: 14px;
  border: 1px solid rgba(148, 163, 184, 0.18);
}

.kanban-columns {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  flex: 1;
  min-height: 0;
}

.kanban-column {
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.25);
  overflow: hidden;
  min-height: 0;
}

.column-pending { border-top: 4px solid #e6a23c; }
.column-processing { border-top: 4px solid #409eff; }
.column-resolved { border-top: 4px solid #67c23a; }
.column-closed { border-top: 4px solid #909399; }

.column-header {
  padding: 14px 16px 12px;
  background: rgba(255, 255, 255, 0.6);
  border-bottom: 1px solid rgba(148, 163, 184, 0.15);
}

.column-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  font-size: 15px;
  color: #1f2933;
}

.column-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.8;
}

.column-pending .column-dot { color: #e6a23c; }
.column-processing .column-dot { color: #409eff; }
.column-resolved .column-dot { color: #67c23a; }
.column-closed .column-dot { color: #909399; }

.column-body {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ticket-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 14px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
  cursor: pointer;
  transition: all 0.18s ease;
  border-left: 4px solid transparent;
}

.ticket-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1);
  border-color: rgba(99, 102, 241, 0.3);
}

.ticket-card.priority-high { border-left-color: #f56c6c; }
.ticket-card.priority-medium { border-left-color: #e6a23c; }
.ticket-card.priority-low { border-left-color: #909399; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.ticket-no {
  font-size: 11px;
  font-weight: 600;
  color: #6366f1;
  letter-spacing: 0.03em;
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 6px;
  line-height: 1.4;
}

.card-description {
  font-size: 12px;
  color: #6b7280;
  line-height: 1.5;
  margin-bottom: 10px;
  min-height: 36px;
}

.card-meta {
  margin-bottom: 10px;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.assignee {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #4b5563;
}

.assignee.unassigned {
  color: #9ca3af;
}

.mini-avatar {
  width: 22px !important;
  height: 22px !important;
  font-size: 11px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: none;
}

.created-time {
  font-size: 11px;
  color: #9ca3af;
}

.card-quick-actions {
  display: flex;
  gap: 6px;
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed #e5e7eb;
}

.empty-column {
  padding: 30px 10px;
}
</style>
