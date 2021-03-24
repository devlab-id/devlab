const crypto = require('crypto')
const { cleanupTmp, execShellAsync } = require('../../../libs/common')
const Deployment = require('../../../models/Deployment')
const { queueAndBuild } = require('../../../libs/applications')
const { setDefaultConfiguration } = require('../../../libs/applications/configuration')
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

    let foundService = false
    let foundDomain = false
    let configChanged = false
    let imageChanged = false

    let forceUpdate = false

    for (const service of services) {
      const running = JSON.parse(service.Spec.Labels.configuration)
      if (running) {
        if (
          running.publish.domain === configuration.publish.domain &&
          running.repository.id !== configuration.repository.id &&
          running.repository.branch !== configuration.repository.branch
        ) {
          foundDomain = true
        }
        if (running.repository.id === configuration.repository.id && running.repository.branch === configuration.repository.branch) {
          const state = await execShellAsync(`docker stack ps ${running.build.container.name} --format '{{ json . }}'`)
          const isError = state.split('\n').filter(n => n).map(s => JSON.parse(s)).filter(n => n.DesiredState !== 'Running')
          if (isError.length > 0) forceUpdate = true
          foundService = true

          const runningWithoutContainer = JSON.parse(JSON.stringify(running))
          delete runningWithoutContainer.build.container

          const configurationWithoutContainer = JSON.parse(JSON.stringify(configuration))
          delete configurationWithoutContainer.build.container

          if (JSON.stringify(runningWithoutContainer.build) !== JSON.stringify(configurationWithoutContainer.build) || JSON.stringify(runningWithoutContainer.publish) !== JSON.stringify(configurationWithoutContainer.publish)) configChanged = true
          if (running.build.container.tag !== configuration.build.container.tag) imageChanged = true
        }
      }
    }
    if (foundDomain) {
      cleanupTmp(configuration.general.workdir)
      reply.code(500).send({ message: 'Domain already used.' })
      return
    }
    if (forceUpdate) {
      imageChanged = false
      configChanged = false
    } else {
      if (foundService && !imageChanged && !configChanged) {
        cleanupTmp(configuration.general.workdir)
        reply.code(500).send({ message: 'Nothing changed, no need to redeploy.' })
        return
      }
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

    queueAndBuild(configuration, services, configChanged, imageChanged)

    reply.code(201).send({ message: 'Deployment queued.' })
  })
}
