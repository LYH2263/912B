import request from '../request'

export const pricingRuleApi = {
  getPricingRules(params) {
    return request({
      url: '/pricing-rules',
      method: 'get',
      params,
    })
  },

  getPricingRule(id) {
    return request({
      url: `/pricing-rules/${id}`,
      method: 'get',
    })
  },

  createPricingRule(data) {
    return request({
      url: '/pricing-rules',
      method: 'post',
      data,
    })
  },

  updatePricingRule(id, data) {
    return request({
      url: `/pricing-rules/${id}`,
      method: 'put',
      data,
    })
  },

  deletePricingRule(id) {
    return request({
      url: `/pricing-rules/${id}`,
      method: 'delete',
    })
  },

  toggleActive(id) {
    return request({
      url: `/pricing-rules/${id}/toggle`,
      method: 'put',
    })
  },
}
