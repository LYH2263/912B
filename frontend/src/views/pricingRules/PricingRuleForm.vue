<template>
  <div class="pricing-rule-form page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">{{ isEdit ? '编辑定价规则' : '新增定价规则' }}</span>
            <span class="card-subtitle">配置条件组合与定价动作</span>
          </div>
          <el-button @click="$router.back()" round>返回</el-button>
        </div>
      </template>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
        style="max-width: 800px;"
      >
        <el-form-item label="规则名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入规则名称" maxlength="200" show-word-limit />
        </el-form-item>

        <el-form-item label="规则描述">
          <el-input v-model="form.description" type="textarea" :rows="2" placeholder="选填：简要说明规则用途" />
        </el-form-item>

        <el-form-item label="优先级" prop="priority">
          <el-input-number v-model="form.priority" :min="0" :max="999" />
          <div class="form-tip">数值越大优先级越高，高优先级规则先匹配</div>
        </el-form-item>

        <el-form-item label="启用状态">
          <el-switch v-model="form.is_active" active-text="启用" inactive-text="禁用" />
        </el-form-item>

        <el-form-item label="条件组合">
          <div class="conditions-wrapper">
            <div
              v-for="(cond, index) in form.conditions"
              :key="index"
              class="condition-row"
            >
              <el-select v-model="cond.field" placeholder="字段" style="width: 160px;">
                <el-option
                  v-for="opt in fieldOptions"
                  :key="opt.value"
                  :label="opt.label"
                  :value="opt.value"
                />
              </el-select>
              <el-select v-model="cond.operator" placeholder="操作符" style="width: 130px;">
                <el-option label="等于" value="=" />
                <el-option label="不等于" value="!=" />
                <el-option label="大于" value=">" />
                <el-option label="大于等于" value=">=" />
                <el-option label="小于" value="<" />
                <el-option label="小于等于" value="<=" />
              </el-select>
              <el-input
                v-if="!isBoolField(cond.field)"
                v-model="cond.value"
                placeholder="值"
                style="width: 180px;"
              />
              <el-select
                v-else
                v-model="cond.value"
                placeholder="值"
                style="width: 180px;"
              >
                <el-option label="是" :value="true" />
                <el-option label="否" :value="false" />
              </el-select>
              <el-button type="danger" plain size="small" @click="removeCondition(index)">
                删除
              </el-button>
            </div>
            <el-button type="primary" plain size="small" @click="addCondition">
              + 添加条件
            </el-button>
            <div class="form-tip">多个条件之间为 AND 关系，所有条件都满足时规则才生效</div>
          </div>
        </el-form-item>

        <el-divider />

        <el-form-item label="动作类型" prop="action_type">
          <el-radio-group v-model="form.action_type">
            <el-radio value="discount_percent">折扣百分比</el-radio>
            <el-radio value="markup_percent">加价百分比</el-radio>
            <el-radio value="fixed_price">固定价格</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="动作值" prop="action_value">
          <el-input-number
            v-model="form.action_value"
            :min="actionValueMin"
            :max="actionValueMax"
            :precision="2"
            :step="actionValueStep"
          />
          <div class="form-tip">{{ actionValueTip }}</div>
        </el-form-item>

        <el-divider />

        <el-form-item label="生效时间">
          <el-date-picker
            v-model="form.starts_at"
            type="datetime"
            placeholder="开始时间（选填）"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 260px;"
          />
          <span style="margin: 0 8px; color: #9ca3af;">至</span>
          <el-date-picker
            v-model="form.ends_at"
            type="datetime"
            placeholder="结束时间（选填）"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 260px;"
          />
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="loading">
            保存
          </el-button>
          <el-button @click="$router.back()">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { pricingRuleApi } from '@/api/modules/pricingRule'

const route = useRoute()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)
const isEdit = ref(false)

const fieldOptions = [
  { label: '分类 ID', value: 'category_id' },
  { label: '库存数量', value: 'stock_quantity' },
  { label: '原价', value: 'price' },
  { label: '成本价', value: 'cost_price' },
  { label: '商品状态', value: 'status' },
  { label: '低库存', value: 'low_stock' },
  { label: '缺货', value: 'out_of_stock' },
]

const form = reactive({
  name: '',
  description: '',
  priority: 0,
  is_active: true,
  conditions: [],
  action_type: 'discount_percent',
  action_value: 10,
  starts_at: null,
  ends_at: null,
})

const rules = {
  name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
  priority: [{ required: true, message: '请输入优先级', trigger: 'blur' }],
  action_type: [{ required: true, message: '请选择动作类型', trigger: 'change' }],
  action_value: [{ required: true, message: '请输入动作值', trigger: 'blur' }],
}

const isBoolField = (field) => {
  return field === 'low_stock' || field === 'out_of_stock'
}

const actionValueMin = computed(() => {
  if (form.action_type === 'discount_percent') return 0.01
  if (form.action_type === 'fixed_price') return 0
  return 0.01
})

const actionValueMax = computed(() => {
  if (form.action_type === 'discount_percent') return 99.99
  return undefined
})

const actionValueStep = computed(() => {
  if (form.action_type === 'fixed_price') return 1
  return 1
})

const actionValueTip = computed(() => {
  if (form.action_type === 'discount_percent') {
    return '输入折扣百分比，例如 10 表示打 9 折（减免 10%）'
  } else if (form.action_type === 'markup_percent') {
    return '输入加价百分比，例如 5 表示售价增加 5%'
  } else {
    return '输入固定售价，单位：元'
  }
})

const addCondition = () => {
  form.conditions.push({
    field: 'category_id',
    operator: '=',
    value: '',
  })
}

const removeCondition = (index) => {
  form.conditions.splice(index, 1)
}

const handleSubmit = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (valid) {
      const submitData = {
        ...form,
        conditions: form.conditions.map((c) => ({
          ...c,
          value: isBoolField(c.field) ? !!c.value : c.value,
        })),
      }

      loading.value = true
      try {
        if (isEdit.value) {
          await pricingRuleApi.updatePricingRule(route.params.id, submitData)
          ElMessage.success('更新成功')
        } else {
          await pricingRuleApi.createPricingRule(submitData)
          ElMessage.success('创建成功')
        }
        router.push('/pricing-rules')
      } catch (error) {
        ElMessage.error(error.response?.data?.message || '操作失败')
      } finally {
        loading.value = false
      }
    }
  })
}

onMounted(async () => {
  if (route.params.id) {
    isEdit.value = true
    try {
      const res = await pricingRuleApi.getPricingRule(route.params.id)
      const data = res.data
      Object.assign(form, {
        name: data.name,
        description: data.description || '',
        priority: data.priority,
        is_active: data.is_active,
        conditions: data.conditions || [],
        action_type: data.action_type,
        action_value: data.action_value,
        starts_at: data.starts_at || null,
        ends_at: data.ends_at || null,
      })
    } catch (error) {
      ElMessage.error('获取规则信息失败')
    }
  }
})
</script>

<style scoped>
.pricing-rule-form {
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

.conditions-wrapper {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.condition-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}
</style>
