const { docker } = require('../../../libs/docker')
const { saveServerLog } = require('../../../libs/logging')

module.exports = async function (fastify) {
  fastify.get('/', async (request, reply) => {
    try {
      const { name } = request.query
      const service = await docker.engine.getService(`${name}_${name}`)
      const logs = (await service.logs({ stdout: true, stderr: true, timestamps: true })).toString().split('\n').map(l => l.slice(8)).filter((a) => a)
      return { logs }
    } catch (error) {
      await saveServerLog(error)
      throw new Error(error)
    }
  })
}
