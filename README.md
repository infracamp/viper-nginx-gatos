# viper-nginx-gatos

Single instance auto config nginx cloudfront including autodeployer.

## TL;DR;

viper nginx parses the docker-service `name` and builds virtual hosts `service-name.xy.com` with  

## Setup

1) Setup a overlay (swarm) network named `gatos-net`:
    ```
    docker network create --driver overlay --attachable gatos-net
    ```
    
2) Create a `cloudfront.env`-file for your cluster setup
    ```
    CONF_CLUSTER_NAME=some fancy name to show on the status page
    CONF_CLUSTER_HOSTNAME=srv.demo.com
    CONF_DEFAULT_HOSTNAME=.srv.demo.com
    CONF_DEPLOY_KEY=secret_deploy_key
    CONF_REGISTRY_PREFIX=registry.gitlab.com/yourOrganisation
    CONF_REGISTRY_LOGIN_USER=user@gitlab.com
    CONF_REGISTRY_LOGIN_PASS=secret_registry_key
    ```
3) Start the cloudfront service
    ```
    docker pull infracamp/viper-nginx-gatos
    docker run -d --env-file cloudfront.env -p 80:80 -p 443:443 --net host --network gatos-net -v /var/run/docker.sock:/var/run/docker.sock --name cloudfront infracamp/viper-nginx-gatos
    ```

## Updating the service

```
curl "http://cloudfront.your.domain/deploy/path/to/your_service?key=secret_deploy_key"
```

or with gitlab (the registry-url will be extracted from request and validated agaist allow auto-deploy):

Will start/update a service `your_service` from `registry.gitlab.com/yourOrganisation/path/to/your_service:latest`.

### Adding provisioning details

```bash
curl -X POST --data-binary @rudl-provision.yml "http://cloudfront.your.domain/deploy/path/to/your_service?key=secret_deploy_key"
```

With content:

```yaml
deploy:
  testing:
    - manager: "cluster.name.org"
      cloudfront:
        hostnames:
        - "x.abc.com"
        acl:
          allow-ip:
          - "192.168.0.0/24"
          allow-user:
          - "userid:cryptedpassword:comment"
```



### Adding additional host names

Login to one manager node and add the label `cf_domain=some.domain.name`:

```
curl "http://cloudfront.your.domain/deploy?key=secret_deploy_key
```

Will start/update a service `your_service` from `registry.gitlab.com/yourOrganisation/path/to/your_service:latest`.


### Auto reloading cloudfront

```bash
#!/bin/bash

docker pull infracamp/viper-nginx-gatos:testing
docker kill cloudfront && docker rm cloudfront

docker run --env-file cloudfront.env -d -p 80:80 -p 443:443 --net host --network gatos-net -v /var/run/docker.sock:/var/run/docker.sock -d --name cloudfront infracamp/viper-nginx-gatos:testing

```


