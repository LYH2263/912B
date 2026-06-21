<template>
  <div class="pricing-rule-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">定价规则</span>
            <span class="card-subtitle">配置商品动态定价规则，支持条件组合与优先级</span>
          </div>
          <el-button type="primary" @click="$router.push('/pricing-rules/create')" round>
            新增规则
          </el-button>
        </div>
      </template>

      <el-table :data="rules" v-loading="loading" style="width: 100%">
        <el-table-column prop="priority" label="优先级" width="90" align="center" />
        <el-table-column prop="name" label="规则名称" width="200" />
        <el-table-column label="条件" min-width="260">
          <template #default="{ row }">
            <div class="conditions-display">
              <el-tag
                v-for="(cond, idx) in row.conditions"
                :key="idx"
                size="small"
                type="info"
                style="margin-right: 4px; margin-bottom: 4px;"
              >
                {{ formatCondition(cond) }}
              </el-tag>
              <span v-if="row.conditions.length === 0" class="muted-text">无条件（全局生效）</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="动作" width="160">
          <template #default="{ row }">
            <el-tag :type="getActionTagType(row.action_type)">
              {{ formatAction(row) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" width="200">
          <template #default="{ row }">
            <span v-if="row.starts_at || row.ends_at" class="muted-text">
              {{ row.starts_at ? formatDate(row.starts_at) : '—' }}
              ~
              {{ row.ends_at ? formatDate(row.ends_at) : '—' }}
            </span>
            <span v-else class="muted-text">永久有效</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.is_active"
              @change="handleToggle(row)"
              active-text="启用"
              inactive-text="禁用"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center">
          <template #default="{ row }">
            <el-button size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchRules"
        @current-change="fetchRules"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { pricingRuleApi } from '@/api/modules/pricingRule'

const router = useRouter()
const rules = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)

const fieldLabelMap = {
  category_id: '分类',
  stock_quantity: '库存数量',
  price: '原价',
  cost_price: '成本价',
  status: '状态',
  low_stock: '低库存',
  out_of_stock: '缺货',
}

const operatorLabelMap = {
  '=': '等于',
  '!=': '不等于',
  '>': '大于',
  '>=': '大于等于',
  '<': '小于',
  '<=': '小于等于',
  'in': '属于',
  'not_in': '不属于',
}

const formatCondition = (cond) => {
  const field = fieldLabelMap[cond.field] || cond.field
  const operator = operatorLabelMap[cond.operator] || cond.operator
  let value = cond.value
  if (cond.field === 'low_stock' || cond.field === 'out_of_stock') {
    value = value ? '是' : '否'
  } else if (Array.isArray(value)) {
    value = value.join(', ')
  }
  return `${field} ${operator} ${value}`
}

const formatAction = (row) => {
  if (row.action_type === 'discount_percent') {
    return `打 ${100 - row.action_value}% 折扣`
  } else if (row.action_type === 'markup_percent') {
    return `加价 ${row.action_value}%`
  } else {
    return `固定价 ¥${row.action_value}`
  }
}

const getActionTagType = (type) => {
  if (type === 'discount_percent') return 'success'
  if (type === 'markup_percent') return 'warning'
  return 'primary'
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const fetchRules = async () => {
  loading.value = true
  try {
    const res = await pricingRuleApi.getPricingRules({
      page: currentPage.value,
      per_page: pageSize.value,
    })
    rules.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取定价规则列表失败')
  } finally {
    loading.value = false
  }
}

const handleEdit = (row) => {
  router.push(`/pricing-rules/${row.id}/edit`)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该规则吗？', '提示', {
      type: 'warning',
    })
    await pricingRuleApi.deletePricingRule(row.id)
    ElMessage.success('删除成功')
    fetchRules()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

const handleToggle = async (row) => {
  try {
    await pricingRuleApi.toggleActive(row.id)
    ElMessage.success(row.is_active ? '已禁用' : '已启用')
    fetchRules()
  } catch (error) {
    ElMessage.error('操作失败')
    fetchRules()
  }
}

onMounted(() => {
  fetchRules()
})
</script>

<style scoped>
.pricing-rule-list {
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

.conditions-display {
  display: flex;
  flex-wrap: wrap;
}

.muted-text {
  color: #9ca3af;
  font-size: 13px;
}
</style>
