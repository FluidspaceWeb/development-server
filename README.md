# Fluidspace Development Server v0.1
This server is a trimmed-down version of the [Fluidspace](https://fluidspace.app) backend for the purpose of easier and faster development of modules. It provides CRUD controller for Data and Props REST API.

It consists of an API client and MongoDB launched in a Docker container. You can use the API client without Docker if your development environment has PHP 7.4+, MongoDB and [PHP-MongoDB driver](https://github.com/mongodb/mongo-php-driver) installed.

Learn more: [What is Fluidspace?](https://gist.github.com/rishiktiwari/645f48422aad7ca7781d1142b3f3b1bd)

Installation video guide: [YouTube](https://youtu.be/MiDPdnPS3bo)

## ✋🏼 Pre-requisites
- Docker (with docker-compose)

## 🔧 Setup
1. Download this repository.

2. Copy the [.env.example](.env.example) and save as `.env`.

3. Set the `DEVELOPER_NAMESPACE` and other ***env*** variables as required.<br>
Namespace is used to prefix all the module names. *Must be space-less, lowercase and alphanumeric.* <br>
Note: You can change the namespace for a new module from the web interface.

4. Rest of the fields can remain unchanged, by default MongoDB has no user and password.

## 🚀 Launching the server
```sh
docker-compose -p fluidspace-dev-server up -d 
```
*First start may take a while as the container images are downloaded and packages are installed.*

The web UI should be accessible on ***http://localhost:1822***

The MongoDB instance can be connected via `localhost:27027`, ideally using [mongosh](https://www.mongodb.com/try/download/shell) or [Compass](https://www.mongodb.com/try/download/compass).

The source code for Web UI is located at `/usr/src/FluidspaceDevApi` in the ***fluidspace-api-client*** docker container.

## 🤔 What Next?
Head-on to [Fluidspace App Module Template](https://github.com/FluidspaceWeb/app-template-vue3) and start developing modules using VueJS.
