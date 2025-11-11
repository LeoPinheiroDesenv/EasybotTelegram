FROM node:18-alpine

# Install postgresql-client for wait script
RUN apk add --no-cache postgresql-client

WORKDIR /app

COPY package*.json ./

RUN npm install

COPY . .

RUN chmod +x wait-for-postgres.sh start.sh

EXPOSE 5000

CMD sh ./start.sh

