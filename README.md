# php proxy for Blazegraph

To create new proxy for the Blazegraph database, clone this project to the new project, go to Gitlab --> Settings --> CI/CD --> Variables and add follwoing variables:

- KUBE_NAMESPACE yournamespace
- POSTGRES_ENABLED false
- TEST_DISABLED true
- SAST_DISABLED true
- PERFORMANCE_DISABLED true
- DOCKER_TLS_CERTDIR ""
- DOCKER_HOST tcp://docker:2375/
- DOCKER_DRIVER overlay2
- CODE_QUALITY_DISABLED true 
- HELM_UPGRADE_EXTRA_ARGS --set ingress.tls.enabled=false --set service.url=yourdomainforthebgproxy.acdh-dev.oeaw.ac.at
- K8S_SECRET_TRIPLESTORE_URL http://193.170.85.102:8080
- K8S_SECRET_TRIPLESTORE_HOST_HEADER triplestore.acdh-dev.oeaw.ac.at
- K8S_SECRET_CACHE_TIMEOUT 604800
- K8S_SECRET_DB_NAME dbname
- K8S_SECRET_DB_USER dbuser 
- K8S_SECRET_DB_PASSWORD dbpassword

