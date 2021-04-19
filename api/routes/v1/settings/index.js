const Settings = require('../../../models/Settings')
const { saveServerLog } = require('../../../libs/logging')

module.exports = async function (fastify) {
  const applicationName = 'coolify'
  const postSchema = {
    body: {
      type: 'object',
      properties: {
        allowRegistration: { type: 'boolean' },
        sendErrors: { type: 'boolean' }
      },
      required: []
    }
  }

  fastify.get('/', async (request, reply) => {
    try {
      let settings = await Settings.findOne({ applicationName }).select('-_id -__v')
      // TODO: Should do better
      if (!settings) {
        settings = {
          applicationName,
          allowRegistration: false
        }
      }
      return {
        settings
      }
    } catch (error) {
      await saveServerLog(error)
      throw new Error(error)
    }
  })

  fastify.post('/', { schema: postSchema }, async (request, reply) => {
    try {
      const settings = await Settings.findOneAndUpdate(
        { applicationName },
        { applicationName, ...request.body },
        { upsert: true, new: true }
      ).select('-_id -__v')
      reply.code(201).send({ settings })
    } catch (error) {
      await saveServerLog(error)
      throw new Error(error)
    }
  })
}
