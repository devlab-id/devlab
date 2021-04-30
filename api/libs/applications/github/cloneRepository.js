const jwt = require('jsonwebtoken')
const axios = require('axios')
const { execShellAsync } = require('../../common')

module.exports = async function (configuration) {
  try {
    const { workdir } = configuration.general
    const { organization, name, branch } = configuration.repository
    const github = configuration.github
    if (!github.installation.id || !github.app.id) {
      throw new Error('Github installation ID is invalid.')
    }
    const githubPrivateKey = process.env.GITHUB_APP_PRIVATE_KEY.replace(/\\n/g, '\n').replace(/"/g, '')

    const payload = {
      iat: Math.round(new Date().getTime() / 1000),
      exp: Math.round(new Date().getTime() / 1000 + 60),
      iss: parseInt(github.app.id)
    }

    const jwtToken = jwt.sign(payload, githubPrivateKey, {
      algorithm: 'RS256'
    })
    const accessToken = await axios({
      method: 'POST',
      url: `https://api.github.com/app/installations/${github.installation.id}/access_tokens`,
      data: {},
      headers: {
        Authorization: 'Bearer ' + jwtToken,
        Accept: 'application/vnd.github.machine-man-preview+json'
      }
    })
    await execShellAsync(
        `mkdir -p ${workdir} && git clone -q -b ${branch} https://x-access-token:${accessToken.data.token}@github.com/${organization}/${name}.git ${workdir}/`
    )
    configuration.build.container.tag = (
      await execShellAsync(`cd ${configuration.general.workdir}/ && git rev-parse HEAD`)
    )
      .replace('\n', '')
      .slice(0, 7)
  } catch (error) {
    throw new Error(error)
  }
}
