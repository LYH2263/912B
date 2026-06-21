<template>
  <div class="flash-sale-page page-shell">
    <el-card>
      <template #header>
        <div class="card-header">
          <div class="card-header-text">
            <span class="card-title">🔥 限时秒杀</span>
            <span class="card-subtitle">手慢无，限时限量抢购</span>
          </div>
        </div>
      </template>

      <div v-if="loading" style="text-align: center; padding: 60px 0;">
        <el-icon class="is-loading" :size="32"><Loading /></el-icon>
      </div>

      <template v-else>
        <div v-if="upcomingList.length > 0" class="section">
          <h3 class="section-title">即将开始</h3>
          <div class="sale-grid">
            <div v-for="item in upcomingList" :key="item.id" class="sale-card upcoming">
              <div class="sale-card-body">
                <div class="sale-product-name">{{ item.product?.name }}</div>
                <div class="sale-price-row">
                  <span class="sale-flash-price">¥{{ item.flash_price }}</span>
                  <span class="sale-original-price">¥{{ item.product?.price }}</span>
                </div>
                <div class="sale-stock-info">活动库存：{{ item.activity_stock }} 件</div>
                <div class="countdown-label">距开始</div>
                <div class="countdown-timer">
                  <span class="countdown-block">{{ getCountdown(item.start_time).hours }}</span>
                  <span class="countdown-sep">:</span>
                  <span class="countdown-block">{{ getCountdown(item.start_time).minutes }}</span>
                  <span class="countdown-sep">:</span>
                  <span class="countdown-block">{{ getCountdown(item.start_time).seconds }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="activeList.length > 0" class="section">
          <h3 class="section-title">正在抢购</h3>
          <div class="sale-grid">
            <div v-for="item in activeList" :key="item.id" class="sale-card active">
              <div class="sale-card-body">
                <div class="sale-product-name">{{ item.product?.name }}</div>
                <div class="sale-price-row">
                  <span class="sale-flash-price">¥{{ item.flash_price }}</span>
                  <span class="sale-original-price">¥{{ item.product?.price }}</span>
                </div>
                <div class="sale-stock-bar-wrap">
                  <el-progress
                    :percentage="item.activity_stock > 0 ? Math.round((item.sold_count / item.activity_stock) * 100) : 100"
                    :stroke-width="18"
                    :text-inside="true"
                    :format="() => `已抢${item.activity_stock > 0 ? Math.round((item.sold_count / item.activity_stock) * 100) : 100}%`"
                    color="linear-gradient(90deg, #f56c6c, #e6393b)"
                  />
                </div>
                <div class="sale-stock-info">剩余 {{ item.remaining_stock }} 件 / 共 {{ item.activity_stock }} 件</div>
                <div class="sale-limit-info">每人限购 {{ item.per_limit }} 件</div>
                <div class="countdown-label">距结束</div>
                <div class="countdown-timer">
                  <span class="countdown-block">{{ getCountdown(item.end_time).hours }}</span>
                  <span class="countdown-sep">:</span>
                  <span class="countdown-block">{{ getCountdown(item.end_time).minutes }}</span>
                  <span class="countdown-sep">:</span>
                  <span class="countdown-block">{{ getCountdown(item.end_time).seconds }}</span>
                </div>
                <div class="sale-action">
                  <el-button
                    v-if="item.remaining_stock > 0"
                    type="danger"
                    round
                    size="large"
                    class="buy-btn"
                    @click="openOrderDialog(item)"
                  >
                    立即抢购
                  </el-button>
                  <el-button v-else type="info" round size="large" disabled class="buy-btn">
                    已抢光
                  </el-button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <el-empty v-if="upcomingList.length === 0 && activeList.length === 0" description="暂无秒杀活动" />
      </template>
    </el-card>

    <el-dialog v-model="orderDialogVisible" title="确认秒杀订单" width="480px" :close-on-click-modal="false">
      <div v-if="selectedItem" class="order-dialog-content">
        <div class="order-product-info">
          <div class="order-product-name">{{ selectedItem.product?.name }}</div>
          <div class="order-price-row">
            <span class="order-flash-price">¥{{ selectedItem.flash_price }}</span>
            <span class="order-original-price">¥{{ selectedItem.product?.price }}</span>
          </div>
        </div>
        <el-form ref="orderFormRef" :model="orderForm" :rules="orderRules" label-width="100px">
          <el-form-item label="购买数量" prop="quantity">
            <el-input-number
              v-model="orderForm.quantity"
              :min="1"
              :max="Math.min(selectedItem.per_limit, selectedItem.remaining_stock)"
            />
          </el-form-item>
          <el-form-item label="收货人" prop="shipping_name">
            <el-input v-model="orderForm.shipping_name" placeholder="请输入收货人姓名" />
          </el-form-item>
          <el-form-item label="联系电话" prop="shipping_phone">
            <el-input v-model="orderForm.shipping_phone" placeholder="请输入联系电话" />
          </el-form-item>
          <el-form-item label="收货地址" prop="shipping_address">
            <el-input v-model="orderForm.shipping_address" type="textarea" :rows="2" placeholder="请输入收货地址" />
          </el-form-item>
          <el-form-item label="订单金额">
            <span class="order-total">¥{{ (selectedItem.flash_price * orderForm.quantity).toFixed(2) }}</span>
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button @click="orderDialogVisible = false">取消</el-button>
        <el-button type="danger" @click="handlePlaceOrder" :loading="orderLoading">确认抢购</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import { flashSaleApi } from '@/api/modules/flashSale'

const loading = ref(true)
const activeList = ref([])
const upcomingList = ref([])
const orderDialogVisible = ref(false)
const orderLoading = ref(false)
const orderFormRef = ref(null)
const selectedItem = ref(null)
let timer = null

const orderForm = reactive({
  quantity: 1,
  shipping_name: '',
  shipping_phone: '',
  shipping_address: '',
  remark: '',
})

const orderRules = {
  quantity: [{ required: true, message: '请输入购买数量', trigger: 'blur' }],
  shipping_name: [{ required: true, message: '请输入收货人姓名', trigger: 'blur' }],
  shipping_phone: [
    { required: true, message: '请输入联系电话', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号码', trigger: 'blur' },
  ],
  shipping_address: [{ required: true, message: '请输入收货地址', trigger: 'blur' }],
}

const getCountdown = (targetTime) => {
  const now = new Date().getTime()
  const target = new Date(targetTime).getTime()
  let diff = Math.max(0, Math.floor((target - now) / 1000))

  const hours = String(Math.floor(diff / 3600)).padStart(2, '0')
  diff %= 3600
  const minutes = String(Math.floor(diff / 60)).padStart(2, '0')
  const seconds = String(diff % 60).padStart(2, '0')

  return { hours, minutes, seconds }
}

const fetchActiveList = async () => {
  try {
    const res = await flashSaleApi.getActiveList()
    activeList.value = res.data.active || []
    upcomingList.value = res.data.upcoming || []
  } catch (error) {
    ElMessage.error('获取秒杀活动失败')
  } finally {
    loading.value = false
  }
}

const openOrderDialog = (item) => {
  selectedItem.value = item
  orderForm.quantity = 1
  orderForm.shipping_name = ''
  orderForm.shipping_phone = ''
  orderForm.shipping_address = ''
  orderForm.remark = ''
  orderDialogVisible.value = true
}

const handlePlaceOrder = async () => {
  if (!orderFormRef.value || !selectedItem.value) return

  await orderFormRef.value.validate(async (valid) => {
    if (valid) {
      orderLoading.value = true
      try {
        await flashSaleApi.placeOrder(selectedItem.value.id, {
          quantity: orderForm.quantity,
          shipping_name: orderForm.shipping_name,
          shipping_phone: orderForm.shipping_phone,
          shipping_address: orderForm.shipping_address,
          remark: orderForm.remark,
        })
        ElMessage.success('抢购成功！订单已创建')
        orderDialogVisible.value = false
        fetchActiveList()
      } catch (error) {
        ElMessage.error(error.response?.data?.message || '抢购失败')
      } finally {
        orderLoading.value = false
      }
    }
  })
}

onMounted(() => {
  fetchActiveList()
  timer = setInterval(() => {
    fetchActiveList()
  }, 30000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.flash-sale-page {
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
  font-size: 20px;
  font-weight: 700;
  color: #111827;
}

.card-subtitle {
  font-size: 12px;
  color: #6b7280;
}

.section {
  margin-bottom: 32px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 16px;
  padding-bottom: 8px;
  border-bottom: 2px solid #f56c6c;
  display: inline-block;
}

.sale-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.sale-card {
  border-radius: 16px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.sale-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.sale-card.upcoming {
  border: 2px solid #e6a23c;
  background: linear-gradient(135deg, #fffbf0, #fff5e0);
}

.sale-card.active {
  border: 2px solid #f56c6c;
  background: linear-gradient(135deg, #fff5f5, #ffe8e8);
}

.sale-card-body {
  padding: 20px;
}

.sale-product-name {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.sale-price-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 12px;
}

.sale-flash-price {
  font-size: 24px;
  font-weight: 700;
  color: #f56c6c;
}

.sale-original-price {
  font-size: 14px;
  color: #9ca3af;
  text-decoration: line-through;
}

.sale-stock-bar-wrap {
  margin-bottom: 8px;
}

.sale-stock-info {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 4px;
}

.sale-limit-info {
  font-size: 12px;
  color: #e6a23c;
  margin-bottom: 12px;
}

.countdown-label {
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 6px;
}

.countdown-timer {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 16px;
}

.countdown-block {
  background: #111827;
  color: #fff;
  font-size: 20px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  min-width: 36px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.countdown-sep {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.sale-action {
  text-align: center;
}

.buy-btn {
  width: 100%;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 2px;
}

.order-dialog-content {
  padding: 0 8px;
}

.order-product-info {
  background: #fff5f5;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 20px;
}

.order-product-name {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.order-price-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.order-flash-price {
  font-size: 22px;
  font-weight: 700;
  color: #f56c6c;
}

.order-original-price {
  font-size: 14px;
  color: #9ca3af;
  text-decoration: line-through;
}

.order-total {
  font-size: 20px;
  font-weight: 700;
  color: #f56c6c;
}
</style>
