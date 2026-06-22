<template>
  <div class="template-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">消息模板管理</span>
            <span class="card-subtitle">配置订单发货通知、库存预警通知等消息模板</span>
          </div>
          <div class="card-header-actions">
            <el-button type="primary" @click="handleCreate">
              <el-icon><Plus /></el-icon>
              新建模板
            </el-button>
          </div>
        </div>
      </template>

      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="模板类型">
          <el-select v-model="filters.type" placeholder="全部类型" clearable style="width: 160px" @change="handleSearch">
            <el-option label="订单发货通知" value="order_shipped" />
            <el-option label="库存预警通知" value="stock_warning" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.is_active" placeholder="全部状态" clearable style="width: 140px" @change="handleSearch">
            <el-option label="启用" :value="true" />
            <el-option label="禁用" :value="false" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="templates" v-loading="loading" style="width: 100%">
        <el-table-column prop="code" label="模板编码" width="140" />
        <el-table-column prop="name" label="模板名称" width="160" />
        <el-table-column label="模板类型" width="140">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)" size="small">
              {{ getTypeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="消息标题" min-width="200" show-overflow-tooltip />
        <el-table-column label="消息内容" min-width="300" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="content-preview">{{ row.content }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-switch
              v-model="row.is_active"
              @change="handleToggle(row)"
              active-text="启用"
              inactive-text="禁用"
              inline-prompt
            />
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170" sortable>
          <template #default="{ row }">
            {{ formatTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="success" link @click="handlePreview(row)">预览</el-button>
            <el-popconfirm title="确定删除该模板吗？" @confirm="handleDelete(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

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

    <el-dialog
      v-model="formVisible"
      :title="isEdit ? '编辑模板' : '新建模板'"
      width="720px"
      @close="handleFormClose"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="110px"
        class="template-form"
      >
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="模板编码" prop="code">
              <el-input v-model="form.code" :disabled="isEdit" placeholder="请输入模板编码，如：order_shipped" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="模板名称" prop="name">
              <el-input v-model="form.name" placeholder="请输入模板名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="模板类型" prop="type">
              <el-select v-model="form.type" placeholder="请选择模板类型" style="width: 100%">
                <el-option label="订单发货通知" value="order_shipped" />
                <el-option label="库存预警通知" value="stock_warning" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否启用">
              <el-switch
                v-model="form.is_active"
                active-text="启用"
                inactive-text="禁用"
                inline-prompt
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="消息标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入消息标题，支持变量占位符" />
        </el-form-item>
        <el-form-item label="变量说明">
          <div class="variables-hint">
            <span v-for="(desc, key) in getVariablesHint(form.type)" :key="key" class="var-chip">
              <code>{{ '{' + key + '}' }}</code> - {{ desc }}
            </span>
          </div>
        </el-form-item>
        <el-form-item label="消息内容" prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="6"
            placeholder="请输入消息内容，支持变量占位符"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="previewVisible" title="模板预览" width="560px">
      <div v-if="previewData" class="preview-content">
        <div class="preview-label">消息标题</div>
        <div class="preview-title">{{ previewData.title }}</div>
        <div class="preview-label" style="margin-top: 16px;">消息内容</div>
        <div class="preview-body">
          <pre>{{ previewData.content }}</pre>
        </div>
      </div>
      <template #footer>
        <el-button type="primary" @click="previewVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import dayjs from 'dayjs'
import { notificationApi } from '@/api/modules/notification'

const templates = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)

const filters = reactive({
  type: '',
  is_active: '',
})

const formVisible = ref(false)
const formRef = ref(null)
const isEdit = ref(false)
const submitLoading = ref(false)
const editId = ref(null)

const form = reactive({
  code: '',
  name: '',
  type: 'order_shipped',
  title: '',
  content: '',
  is_active: true,
  variables: [],
})

const rules = {
  code: [{ required: true, message: '请输入模板编码', trigger: 'blur' }],
  name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择模板类型', trigger: 'change' }],
  title: [{ required: true, message: '请输入消息标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入消息内容', trigger: 'blur' }],
}

const previewVisible = ref(false)
const previewData = ref(null)

const typeVariablesMap = {
  order_shipped: {
    order_no: '订单号',
    order_id: '订单ID',
    final_amount: '订单金额',
    shipping_name: '收货人',
    shipping_phone: '联系电话',
    shipping_address: '收货地址',
    items: '商品列表',
    shipped_at: '发货时间',
  },
  stock_warning: {
    product_name: '商品名称',
    product_sku: '商品SKU',
    product_id: '商品ID',
    stock_quantity: '当前库存',
    low_stock_threshold: '预警阈值',
    warning_time: '预警时间',
  },
}

const getVariablesHint = (type) => typeVariablesMap[type] || {}

const getTypeLabel = (type) => {
  const map = {
    order_shipped: '订单发货通知',
    stock_warning: '库存预警通知',
  }
  return map[type] || type
}

const getTypeTagType = (type) => {
  const map = {
    order_shipped: 'success',
    stock_warning: 'warning',
  }
  return map[type] || 'info'
}

const formatTime = (time) => dayjs(time).format('YYYY-MM-DD HH:mm')

const fetchTemplates = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (filters.type) params.type = filters.type
    if (filters.is_active !== '') params.is_active = filters.is_active

    const res = await notificationApi.getTemplates(params)
    templates.value = res.data || []
    total.value = res.meta?.total || 0
  } catch (e) {
    ElMessage.error('获取模板列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchTemplates()
}

const handleReset = () => {
  filters.type = ''
  filters.is_active = ''
  currentPage.value = 1
  fetchTemplates()
}

const handleSizeChange = () => {
  currentPage.value = 1
  fetchTemplates()
}

const handleCurrentChange = () => {
  fetchTemplates()
}

const resetForm = () => {
  Object.assign(form, {
    code: '',
    name: '',
    type: 'order_shipped',
    title: '',
    content: '',
    is_active: true,
    variables: [],
  })
  editId.value = null
  if (formRef.value) {
    formRef.value.clearValidate()
  }
}

const handleCreate = () => {
  isEdit.value = false
  resetForm()
  formVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  editId.value = row.id
  Object.assign(form, {
    code: row.code,
    name: row.name,
    type: row.type,
    title: row.title,
    content: row.content,
    is_active: row.is_active,
    variables: row.variables || [],
  })
  formVisible.value = true
}

const handleFormClose = () => {
  resetForm()
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitLoading.value = true
    try {
      form.variables = Object.keys(getVariablesHint(form.type))
      if (isEdit.value) {
        await notificationApi.updateTemplate(editId.value, {
          name: form.name,
          type: form.type,
          title: form.title,
          content: form.content,
          is_active: form.is_active,
          variables: form.variables,
        })
        ElMessage.success('模板更新成功')
      } else {
        await notificationApi.createTemplate({
          code: form.code,
          name: form.name,
          type: form.type,
          title: form.title,
          content: form.content,
          is_active: form.is_active,
          variables: form.variables,
        })
        ElMessage.success('模板创建成功')
      }
      formVisible.value = false
      fetchTemplates()
    } catch (e) {
      // 错误已由拦截器处理
    } finally {
      submitLoading.value = false
    }
  })
}

const handleToggle = async (row) => {
  try {
    await notificationApi.toggleTemplate(row.id)
    ElMessage.success(row.is_active ? '模板已启用' : '模板已禁用')
    fetchTemplates()
  } catch (e) {
    row.is_active = !row.is_active
  }
}

const handleDelete = async (row) => {
  try {
    await notificationApi.deleteTemplate(row.id)
    ElMessage.success('删除成功')
    fetchTemplates()
  } catch (e) {
    // 错误已由拦截器处理
  }
}

const getSampleData = (type) => {
  if (type === 'order_shipped') {
    return {
      order_no: 'ORDER20240101000001',
      order_id: 123,
      final_amount: 299.00,
      shipping_name: '张三',
      shipping_phone: '138****8888',
      shipping_address: '北京市朝阳区某某街道123号',
      items: '商品A x2、商品B x1',
      shipped_at: '2024-01-01 10:00',
    }
  }
  return {
    product_name: '示例商品',
    product_sku: 'SKU001',
    product_id: 456,
    stock_quantity: 3,
    low_stock_threshold: 10,
    warning_time: '2024-01-01 10:00',
  }
}

const handlePreview = (row) => {
  const sample = getSampleData(row.type)
  let title = row.title
  let content = row.content
  Object.keys(sample).forEach(key => {
    const placeholder = `{${key}}`
    title = title.replaceAll(placeholder, String(sample[key]))
    content = content.replaceAll(placeholder, String(sample[key]))
  })
  previewData.value = { title, content }
  previewVisible.value = true
}

onMounted(() => {
  fetchTemplates()
})
</script>

<style scoped>
.template-list {
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

.content-preview {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  color: #6b7280;
  font-size: 13px;
  white-space: pre-wrap;
}

.variables-hint {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.var-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  background-color: #f3f4ff;
  border-radius: 6px;
  font-size: 12px;
  color: #4f46e5;
}

.var-chip code {
  background: transparent;
  padding: 0;
  margin-right: 4px;
  color: #4f46e5;
  font-weight: 500;
}

.preview-content {
  padding: 4px;
}

.preview-label {
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 6px;
}

.preview-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  padding: 10px 14px;
  background: linear-gradient(135deg, #eff6ff, #f5f3ff);
  border-radius: 8px;
}

.preview-body {
  margin-top: 8px;
}

.preview-body pre {
  margin: 0;
  padding: 14px;
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
