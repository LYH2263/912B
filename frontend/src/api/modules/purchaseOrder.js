import request from '../request'

export const purchaseOrderApi = {
  getPurchaseOrders(params) {
    return request({
      url: '/purchase-orders',
      method: 'get',
      params,
    })
  },

  getPurchaseOrder(id) {
    return request({
      url: `/purchase-orders/${id}`,
      method: 'get',
    })
  },

  createPurchaseOrder(data) {
    return request({
      url: '/purchase-orders',
      method: 'post',
      data,
    })
  },

  updatePurchaseOrder(id, data) {
    return request({
      url: `/purchase-orders/${id}`,
      method: 'put',
      data,
    })
  },

  deletePurchaseOrder(id) {
    return request({
      url: `/purchase-orders/${id}`,
      method: 'delete',
    })
  },

  submitPurchaseOrder(id) {
    return request({
      url: `/purchase-orders/${id}/submit`,
      method: 'put',
    })
  },

  stockInPurchaseOrder(id, data) {
    return request({
      url: `/purchase-orders/${id}/stock-in`,
      method: 'post',
      data,
    })
  },
}
