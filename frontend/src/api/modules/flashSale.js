import request from '../request'

export const flashSaleApi = {
  getFlashSales(params) {
    return request({
      url: '/flash-sales',
      method: 'get',
      params,
    })
  },

  getFlashSale(id) {
    return request({
      url: `/flash-sales/${id}`,
      method: 'get',
    })
  },

  createFlashSale(data) {
    return request({
      url: '/flash-sales',
      method: 'post',
      data,
    })
  },

  updateFlashSale(id, data) {
    return request({
      url: `/flash-sales/${id}`,
      method: 'put',
      data,
    })
  },

  deleteFlashSale(id) {
    return request({
      url: `/flash-sales/${id}`,
      method: 'delete',
    })
  },

  getActiveList() {
    return request({
      url: '/flash-sales-active',
      method: 'get',
    })
  },

  placeOrder(flashSaleId, data) {
    return request({
      url: `/flash-sales/${flashSaleId}/order`,
      method: 'post',
      data,
    })
  },
}
