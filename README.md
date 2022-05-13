# php proxy for Blazegraph

## Deployment on Kubernetes

To create new proxy for the Blazegraph database, clone this project to the new project, go to Rancher --> Workflow --> Variables and add follwoing variables:

- TRIPLESTORE_URL http://193.170.85.102:8080/dbname
- TRIPLESTORE_HOST_HEADER triplestore.acdh-dev.oeaw.ac.at
- CACHE_TIMEOUT 604800
- DB_USER dbuser 
- DB_PASSWORD dbpassword
- MAX_CACHE_SIZE maximumCacheSizeInBytes
- WEB_CONCURRENCY 8

Some settings must be set up by hand in Rancher:

* The `ID` label with a Redmine issue ID
* Some health check settings (type `Command run inside the container exits with status 0`, command `/app/kubernetesCheck.sh`, it may also make sense to rise the default check interval to at least 60 seconds)

## Deployment on docker-tools

* Use `PHPL` environment
* Set `CACHE_TIMEOUT`, `DB_PASSWORD`, `DB_USER`, `TRIPLESTORE_HOST_HEADER` and `TRIPLESTORE_URL` (and optionally `CACHE_MAX_SIZE`) env vars in the config.yaml using the `EnvVars` configuration property.

