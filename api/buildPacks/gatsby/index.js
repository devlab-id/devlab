const fs = require('fs').promises
const { buildImage } = require('../helpers')
const { streamEvents, docker } = require('../../libs/docker')

//    'HEALTHCHECK --timeout=10s --start-period=10s --interval=5s CMD curl -I -s -f http://localhost/ || exit 1',
const publishStaticDocker = (configuration) => {
  return [
    'FROM nginx:stable-alpine',
    'COPY nginx.conf /etc/nginx/nginx.conf',
    'WORKDIR /usr/share/nginx/html',
    `COPY --from=${configuration.build.container.name}:${configuration.build.container.tag}-cache /usr/src/app/${configuration.publish.directory} ./`,
    'EXPOSE 80',
    'CMD ["nginx", "-g", "daemon off;"]'
  ].join('\n')
}

module.exports = async function (configuration) {
  await buildImage(configuration, true)
  await fs.writeFile(`${configuration.general.workdir}/Dockerfile`, publishStaticDocker(configuration))
  const stream = await docker.engine.buildImage(
    { src: ['.'], context: configuration.general.workdir },
    { t: `${configuration.build.container.name}:${configuration.build.container.tag}` }
  )
  await streamEvents(stream, configuration)
}
