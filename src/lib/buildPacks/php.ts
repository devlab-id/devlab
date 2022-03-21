import { buildImage } from '$lib/docker';
import { promises as fs } from 'fs';

const createDockerfile = async (data, image): Promise<void> => {
	const { workdir, baseDirectory } = data;
	const Dockerfile: Array<string> = [];
	Dockerfile.push(`FROM ${image}`);
	Dockerfile.push(`LABEL coolify.image=true`);
	if (data.phpModules?.length > 0) {
		Dockerfile.push(
			`ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/`
		);
		Dockerfile.push(`RUN chmod +x /usr/local/bin/install-php-extensions`);
		Dockerfile.push(`RUN /usr/local/bin/install-php-extensions ${data.phpModules.join(' ')}`);
	}
	// Dockerfile.push('RUN a2enmod rewrite');
	// Dockerfile.push(`ENV APACHE_DOCUMENT_ROOT /app`);
	// Dockerfile.push(`RUN sed -ri -e 's!/var/www/html!/app!g' /etc/apache2/sites-available/*.conf`);
	// Dockerfile.push(`RUN sed -ri -e 's!/var/www/!/app!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf`)
	Dockerfile.push('WORKDIR /app');
	Dockerfile.push(`COPY .${baseDirectory || ''} /app`);
	Dockerfile.push(`COPY /.htaccess .`);
	Dockerfile.push(`COPY /entrypoint.sh /entrypoint.sh`);
	Dockerfile.push(`EXPOSE 80`);
	await fs.writeFile(`${workdir}/Dockerfile`, Dockerfile.join('\n'));
};

export default async function (data) {
	try {
		const image = 'webdevops/php-nginx';
		await createDockerfile(data, image);
		await buildImage(data);
	} catch (error) {
		throw error;
	}
}
