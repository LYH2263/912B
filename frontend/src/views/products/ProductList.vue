<template>
  <div class="product-list page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">商品列表</span>
            <span class="card-subtitle">管理在售商品、状态与库存情况</span>
          </div>
          <el-button type="primary" @click="$router.push('/products/create')" round>
            新增商品
          </el-button>
        </div>
      </template>
      
      <el-table :data="products" v-loading="loading" style="width: 100%">
        <el-table-column prop="name" label="商品名称" width="200" />
        <el-table-column prop="sku" label="SKU" width="150" />
        <el-table-column label="价格" width="180">
          <template #default="{ row }">
            <div class="price-wrapper">
              <div v-if="row.has_discount" class="price-has-discount">
                <span class="price-current">¥{{ row.final_price }}</span>
                <span class="price-original">¥{{ row.original_price }}</span>
                <el-tag size="small" type="danger" effect="light">
                  -{{ row.discount_percent }}%
                </el-tag>
              </div>
              <div v-else-if="row.has_markup" class="price-has-markup">
                <span class="price-current">¥{{ row.final_price }}</span>
                <span class="price-original">¥{{ row.original_price }}</span>
                <el-tag size="small" type="warning" effect="light">
                  加价
                </el-tag>
              </div>
              <div v-else>
                <span class="price-current">¥{{ row.original_price }}</span>
              </div>
            </div>
            <div v-if="row.applied_rule_name" class="applied-rule-name">
              规则：{{ row.applied_rule_name }}
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="stock_quantity" label="库存" width="100" />
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">
              {{ getStatusText(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300">
          <template #default="{ row }">
            <el-button size="small" @click="handleViewQualityInspections(row)">质检记录</el-button>
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
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        style="margin-top: 20px; justify-content: flex-end;"
      />
    </el-card>

    <el-dialog v-model="qualityVisible" :title="`${currentProduct?.name || ''} - 历史质检记录`" width="900px" top="5vh">
      <el-table :data="qualityInspections" v-loading="qualityLoading" style="width: 100%">
        <el-table-column prop="batch_no" label="批次号" width="180" />
        <el-table-column label="关联采购单" width="180">
          <template #default="{ row }">
            <span v-if="row.purchase_order">
              {{ row.purchase_order.purchase_order_no }}
            </span>
            <span v-else style="color: #9ca3af">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="qualified_quantity" label="合格数量" width="100" align="center">
          <template #default="{ row }">
            <span style="color: #67c23a; font-weight: 500">{{ row.qualified_quantity }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="unqualified_quantity" label="不合格数量" width="100" align="center">
          <template #default="{ row }">
            <span :style="{ color: row.unqualified_quantity > 0 ? '#f56c6c' : '', fontWeight: 500 }">
              {{ row.unqualified_quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="total_quantity" label="总数" width="80" align="center" />
        <el-table-column prop="pass_rate" label="合格率" width="120" align="center">
          <template #default="{ row }">
            <el-progress
              :percentage="row.pass_rate"
              :color="row.pass_rate >= 95 ? '#67c23a' : row.pass_rate >= 80 ? '#e6a23c' : '#f56c6c'"
              :stroke-width="8"
            />
          </template>
        </el-table-column>
        <el-table-column prop="inspector" label="质检员" width="100" />
        <el-table-column prop="inspection_date" label="质检日期" width="120" />
        <el-table-column label="不合格原因" min-width="150">
          <template #default="{ row }">
            <span v-if="row.unqualified_reason">{{ row.unqualified_reason }}</span>
            <span v-else style="color: #9ca3af">-</span>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-model:current-page="qualityPage"
        v-model:page-size="qualityPageSize"
        :total="qualityTotal"
        :page-sizes="[5, 10, 20]"
        layout="total, prev, pager, next"
        @size-change="handleQualityPageChange"
        @current-change="handleQualityPageChange"
        style="margin-top: 16px; justify-content: flex-end"
      />
      <template #footer>
        <el-button @click="qualityVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { productApi } from '@/api/modules/product'
import { qualityInspectionApi } from '@/api/modules/qualityInspection'

const router = useRouter()
const products = ref([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)

const qualityVisible = ref(false)
const qualityLoading = ref(false)
const qualityInspections = ref([])
const qualityPage = ref(1)
const qualityPageSize = ref(5)
const qualityTotal = ref(0)
const currentProduct = ref(null)

const getStatusType = (status) => {
  const map = {
    active: 'success',
    inactive: 'info',
    sold_out: 'danger',
  }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = {
    active: '上架',
    inactive: '下架',
    sold_out: '售罄',
  }
  return map[status] || status
}

const fetchProducts = async () => {
  loading.value = true
  try {
    const res = await productApi.getProducts({
      page: currentPage.value,
      per_page: pageSize.value,
    })
    products.value = res.data
    total.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取商品列表失败')
  } finally {
    loading.value = false
  }
}

const handleEdit = (row) => {
  router.push(`/products/${row.id}/edit`)
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm('确定要删除该商品吗？', '提示', {
      type: 'warning',
    })
    await productApi.deleteProduct(row.id)
    ElMessage.success('删除成功')
    fetchProducts()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
    }
  }
}

const handleSizeChange = () => {
  fetchProducts()
}

const handleCurrentChange = () => {
  fetchProducts()
}

const handleViewQualityInspections = async (row) => {
  currentProduct.value = row
  qualityPage.value = 1
  qualityVisible.value = true
  await fetchQualityInspections(row.id)
}

const fetchQualityInspections = async (productId) => {
  qualityLoading.value = true
  try {
    const res = await qualityInspectionApi.getProductQualityInspections(productId, {
      page: qualityPage.value,
      per_page: qualityPageSize.value,
    })
    qualityInspections.value = res.data
    qualityTotal.value = res.meta.total
  } catch (error) {
    ElMessage.error('获取质检记录失败')
  } finally {
    qualityLoading.value = false
  }
}

const handleQualityPageChange = () => {
  if (currentProduct.value) {
    fetchQualityInspections(currentProduct.value.id)
  }
}

onMounted(() => {
  fetchProducts()
})
</script>

<style scoped>
.product-list {
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

.price-wrapper {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.price-current {
  font-size: 16px;
  font-weight: 600;
  color: #ef4444;
}

.price-has-markup .price-current {
  color: #f59e0b;
}

.price-original {
  font-size: 12px;
  color: #9ca3af;
  text-decoration: line-through;
  margin-left: 6px;
}

.price-has-discount,
.price-has-markup {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.applied-rule-name {
  font-size: 11px;
  color: #6366f1;
  margin-top: 2px;
}
</style>
