const fs = require('fs').promises
const { streamEvents, docker } = require('../libs/docker')

async function buildImage (configuration) {
  let dockerFile = `
                # build
                FROM node:lts
                WORKDIR /usr/src/app
                COPY package*.json .
                `
  if (configuration.build.command.installation) {
    dockerFile += `RUN ${configuration.build.command.installation}
                `
  }
  dockerFile += `COPY . .
            RUN ${configuration.build.command.build}`

  await fs.writeFile(`${configuration.general.workdir}/Dockerfile`, dockerFile)
  const stream = await docker.engine.buildImage(
    { src: ['.'], context: configuration.general.workdir },
    { t: `${configuration.build.container.name}:${configuration.build.container.tag}` }
  )
  await streamEvents(stream, configuration)
}

module.exports = {
  buildImage
}
