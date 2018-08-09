# viper-nginx-gatos
Single instance auto config nginx cloudfront


## Running

```
docker run --net host -v /var/run/docker.sock:/var/run/docker.sock --name cloudfront -e CONF_HOSTNAME=.srv.xy.com infracamp/viper-nginx-gatos
```
