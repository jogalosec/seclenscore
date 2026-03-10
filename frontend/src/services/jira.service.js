/**
 * Servicio JIRA — Issues EAS + transiciones.
 */
import api from './api.service.js'

const JiraService = {
  getIssuesEas() { return api.get('/api/getIssuesEas') },
  newIssueArquitectura(data) { return api.post('/api/newIssueArquitectura', data) },
  getIssueDetail(key) { return api.get('/api/getIssueDetail', { params: { key } }) },
  getTransitions(key) { return api.get('/api/getJiraTransitions', { params: { key } }) },
  transitionIssue(key, transition_id) { return api.post('/api/transitionJiraIssue', null, { params: { key, transition_id } }) },
}

export default JiraService
