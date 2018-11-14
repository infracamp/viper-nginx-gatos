
## Remove all down nodes form cluster

```
docker node rm `docker node ls | grep Down | awk '{print $1;}'`
```
