const { docker } = require('../../../libs/docker')
const Deployment = require('../../../models/Deployment')
const ServerLog = require('../../../models/Logs/Server')

module.exports = async function (fastify) {
  fastify.get('/', async (request, reply) => {
    try {
      const latestDeployments = await Deployment.aggregate([
        {
          $sort: { createdAt: -1 }
        },
        {
          $group:
          {
            _id: {
              repoId: '$repoId',
              branch: '$branch'
            },
            createdAt: { $last: '$createdAt' },
            progress: { $first: '$progress' }
          }
        }
      ])

      const serverLogs = await ServerLog.find()
      const services = await docker.engine.listServices()

      let applications = services.filter(r => r.Spec.Labels.managedBy === 'coolify' && r.Spec.Labels.type === 'application' && r.Spec.Labels.configuration)
      let databases = services.filter(r => r.Spec.Labels.managedBy === 'coolify' && r.Spec.Labels.type === 'database' && r.Spec.Labels.configuration)
      applications = applications.map(r => {
        if (JSON.parse(r.Spec.Labels.configuration)) {
          const configuration = JSON.parse(r.Spec.Labels.configuration)
          const status = latestDeployments.find(l => configuration.repository.id === l._id.repoId && configuration.repository.branch === l._id.branch)
          if (status && status.progress) r.progress = status.progress
          r.Spec.Labels.configuration = configuration
          return r
        }
        return {}
      })
      databases = databases.map(r => {
        const configuration = r.Spec.Labels.configuration ? JSON.parse(r.Spec.Labels.configuration) : null
        r.Spec.Labels.configuration = configuration
        return r
      })
      applications = [...new Map(applications.map(item => [item.Spec.Labels.configuration.publish.domain + item.Spec.Labels.configuration.publish.path, item])).values()]
      return {
        serverLogs,
        applications: {
          deployed: applications
        },
        databases: {
          deployed: databases
        }
      }
    } catch (error) {
      if (error.code === 'ENOENT' && error.errno === -2) {
        throw new Error(`Docker service unavailable at ${error.address}.`)
      } else {
        throw { error, type: 'server' }
      }
    }
  })
}
