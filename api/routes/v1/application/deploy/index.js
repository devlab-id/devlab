
const Deployment = require('../../../../models/Deployment')
const ApplicationLog = require('../../../../models/Logs/Application')
const { verifyUserId, cleanupTmp } = require('../../../../libs/common')
const { purgeImagesContainers } = require('../../../../libs/applications/cleanup')
const { queueAndBuild } = require('../../../../libs/applications')
const { setDefaultConfiguration, precheckDeployment } = require('../../../../libs/applications/configuration')
const { docker } = require('../../../../libs/docker')
const { saveServerLog } = require('../../../../libs/logging')
const cloneRepository = require('../../../../libs/applications/github/cloneRepository')

module.exports = async function (fastify) {
  fastify.post('/', async (request, reply) => {
    let configuration
    try {
      await verifyUserId(request.headers.authorization)
    } catch (error) {
      reply.code(500).send({ error: 'Invalid request' })
      return
    }
    try {
      const services = (await docker.engine.listServices()).filter(r => r.Spec.Labels.managedBy === 'coolify' && r.Spec.Labels.type === 'application')
      configuration = setDefaultConfiguration(request.body)
      if (!configuration) {
        throw new Error('Whaat?')
      }
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

      reply.code(201).send({ message: 'Deployment queued.', nickname: configuration.general.nickname, name: configuration.build.container.name, deployId: configuration.general.deployId })
      await queueAndBuild(configuration, imageChanged)
    } catch (error) {
      const { id, organization, name, branch } = configuration.repository
      const { domain } = configuration.publish
      const { deployId } = configuration.general
      await Deployment.findOneAndUpdate(
        { repoId: id, branch, deployId, organization, name, domain },
        { repoId: id, branch, deployId, organization, name, domain, progress: 'failed' })
      if (error.name) {
        if (error.message && error.stack) await saveServerLog(error)
        if (reply.sent) await new ApplicationLog({ repoId: id, branch, deployId, event: `[ERROR 😖]: ${error.stack}` }).save()
      }
      throw new Error(error)
    } finally {
      cleanupTmp(configuration.general.workdir)
      await purgeImagesContainers(configuration)
    }
  })
}
