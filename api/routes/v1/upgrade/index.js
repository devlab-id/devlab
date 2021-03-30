const { execShellAsync } = require('../../../libs/common')
const { saveServerLog } = require('../../../libs/logging')

module.exports = async function (fastify) {
  fastify.get('/', async (request, reply) => {
    const upgradeP1 = await execShellAsync('bash ./upgrade.sh upgrade-p1')
    await saveServerLog({ event: upgradeP1, type: 'UPGRADE-P-1' })
    reply.code(200).send('I\'m trying, okay?')
    const upgradeP2 = await execShellAsync('bash ./upgrade.sh upgrade-p2')
    await saveServerLog({ event: upgradeP2, type: 'UPGRADE-P-2' })
  })
}
