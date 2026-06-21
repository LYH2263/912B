import request from '../request'

export const memberApi = {
  getMemberInfo() {
    return request({
      url: '/member',
      method: 'get',
    })
  },

  getPointLogs(params) {
    return request({
      url: '/member/point-logs',
      method: 'get',
      params,
    })
  },

  calculateDiscount(data) {
    return request({
      url: '/member/calculate-discount',
      method: 'post',
      data,
    })
  },
}
