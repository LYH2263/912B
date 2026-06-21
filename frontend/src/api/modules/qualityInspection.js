import request from '../request'

export const qualityInspectionApi = {
  getQualityInspections(params) {
    return request({
      url: '/quality-inspections',
      method: 'get',
      params,
    })
  },

  getQualityInspection(id) {
    return request({
      url: `/quality-inspections/${id}`,
      method: 'get',
    })
  },

  createQualityInspection(data) {
    return request({
      url: '/quality-inspections',
      method: 'post',
      data,
    })
  },

  updateQualityInspection(id, data) {
    return request({
      url: `/quality-inspections/${id}`,
      method: 'put',
      data,
    })
  },

  deleteQualityInspection(id) {
    return request({
      url: `/quality-inspections/${id}`,
      method: 'delete',
    })
  },

  getProductQualityInspections(productId, params) {
    return request({
      url: `/products/${productId}/quality-inspections`,
      method: 'get',
      params,
    })
  },
}
