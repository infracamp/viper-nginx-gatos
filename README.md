# viper-nginx-gatos

Single instance auto config nginx cloudfront including autodeployer.

## TL;DR;

viper nginx parses the docker-service `name` and builds virtual hosts `service-name.xy.com` with  

## Setup

1) Setup a overlay (swarm) network named `gatos-net`:
    ```
    docker network create --driver overlay --attachable gatos-net
    ```
    
2) Create a `config.env`-file for your cluster setup
    ```
    CONF_DEFAULT_HOSTNAME=.srv.demo.com
    CONF_DEPLOY_KEY=secret_deploy_key
    CONF_REGISTRY_PREFIX=registry.gitlab.com/yourOrganisation
    CONF_REGISTRY_LOGIN_USER=user@gitlab.com
    CONF_REGISTRY_LOGIN_PASS=secret_registry_key
    ```
3) Start the cloudfront service
    ```
    docker run --env-file config.env --net host --network gatos-net -v /var/run/docker.sock:/var/run/docker.sock --name cloudfront infracamp/viper-nginx-gatos
    ```

## Updating the service

```
curl "http://cloudfront.your.domain/deploy/path/to/your_service?key=secret_deploy_key"
```

Will start/update a service `your_service` from `registry.gitlab.com/yourOrganisation/path/to/your_service:latest`.

## Running


gatos.env
```
CONF_HOSTNAME=.srv.xy.com

```


```
docker run --net host -v /var/run/docker.sock:/var/run/docker.sock --network gatos-net --name cloudfront -e  infracamp/viper-nginx-gatos
```

