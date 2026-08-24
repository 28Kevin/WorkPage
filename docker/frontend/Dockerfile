# Servidor de desarrollo de Vite para la SPA Vue 3.
FROM node:22-alpine

RUN apk add --no-cache bash

WORKDIR /app

# node_modules vive en un volumen propio del contenedor (binarios nativos Linux).
COPY docker/frontend/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

EXPOSE 5173

ENTRYPOINT ["entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
