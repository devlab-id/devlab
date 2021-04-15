const crypto = require('crypto')
const { cleanupTmp } = require('../../../libs/common')
const Deployment = require('../../../models/Deployment')
const { queueAndBuild } = require('../../../libs/applications')
const { setDefaultConfiguration, precheckDeployment } = require('../../../libs/applications/configuration')
const { docker } = require('../../../libs/docker')
const cloneRepository = require('../../../libs/applications/github/cloneRepository')

module.exports = async function (fastify) {
  // TODO: Add this to fastify plugin
  const postSchema = {
    body: {
      type: 'object',
      properties: {
        ref: { type: 'string' },
        repository: {
          type: 'object',
          properties: {
            id: { type: 'number' },
            full_name: { type: 'string' }
          },
          required: ['id', 'full_name']
        },
        installation: {
          type: 'object',
          properties: {
            id: { type: 'number' }
          },
          required: ['id']
        }
      },
      required: ['ref', 'repository', 'installation']
    }
  }
  fastify.post('/', { schema: postSchema }, async (request, reply) => {
    const hmac = crypto.createHmac('sha256', fastify.config.GITHUP_APP_WEBHOOK_SECRET)
    const digest = Buffer.from('sha256=' + hmac.update(JSON.stringify(request.body)).digest('hex'), 'utf8')
    const checksum = Buffer.from(request.headers['x-hub-signature-256'], 'utf8')
    if (checksum.length !== digest.length || !crypto.timingSafeEqual(digest, checksum)) {
      reply.code(500).send({ error: 'Invalid request' })
      return
    }

    if (request.headers['x-github-event'] !== 'push') {
      reply.code(500).send({ error: 'Not a push event.' })
      return
    }
    try {
      const services = (await docker.engine.listServices()).filter(r => r.Spec.Labels.managedBy === 'coolify' && r.Spec.Labels.type === 'application')

      let configuration = services.find(r => {
        if (request.body.ref.startsWith('refs')) {
          const branch = request.body.ref.split('/')[2]
          if (
            JSON.parse(r.Spec.Labels.configuration).repository.id === request.body.repository.id &&
            JSON.parse(r.Spec.Labels.configuration).repository.branch === branch
          ) {
            return r
          }
        }

        return null
      })
      if (!configuration) {
        reply.code(500).send({ error: 'No configuration found.' })
        return
      }

      configuration = setDefaultConfiguration(JSON.parse(configuration.Spec.Labels.configuration))
      await cloneRepository(configuration)
      const { foundService, imageChanged, configChanged, forceUpdate } = await precheckDeployment({ services, configuration })

      if (foundService && !forceUpdate && !imageChanged && !configChanged) {
        cleanupTmp(configuration.general.workdir)
        reply.code(500).send({ message: 'Nothing changed, no need to redeploy.' })
        return
      }
      const alreadyQueued = await Deployment.find({
        repoId: configuration.repository.id,
        branch: configuration.repository.branch,
        organization: configuration.repository.organization,
        name: configuration.repository.name,
        domain: configuration.publish.domain,
        progress: { $in: ['queued', 'inprogress'] }
      })

      if (alreadyQueued.length > 0) {
        reply.code(200).send({ message: 'Already in the queue.' })
        return
      }

      queueAndBuild(configuration, imageChanged)

      reply.code(201).send({ message: 'Deployment queued.', nickname: configuration.general.nickname, name: configuration.build.container.name })
    } catch (error) {
      throw { error, type: 'server' }
    }
  })
}
