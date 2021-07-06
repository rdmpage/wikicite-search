# WikiCite Search

A search engine for publications in Wikidata. Rather than directly search Wikidata we download a subset of items corresponding to publications (for example, those relevant to taxonomy), convert to a simple JSON format (CSL) then add to an Elasticsearch index. We support basic search by metadata, and also a reconciliation service.

Goal is to have a simple search interface that (a) finds an article if we have it, and (b) display links to full text if available.

## API




## Elasticsearch

Local server for development and debugging set up using Docker.

Remote set up on Bitnami. Note that following [Bitnami’s instructions](https://docs.bitnami.com/google/apps/elasticsearch/administration/connect-remotely/) we need to do this:

```
network.host: Specify the hostname or IP address where the server will be accessible. Set it to 0.0.0.0 to listen on every interface.
```

To do this launch the SSH console in Bitnami, then:

```
sudo nano /opt/bitnami/elasticsearch/config/elasticsearch.yml
```

and edit

```
network:
  host: 127.0.0.1
```


Then we make sure to open the port using the [Google Console](https://docs.bitnami.com/google/faq/administration/use-firewall/). For direct connection to Elasticsearch (not recommended) open 9200, for connection via Apache with basic authentication open port 80 (note that this means you will need to add ELASTIC_USERNAME and ELASTIC_PASSWORD to the Heroku Config Vars. To set up Apache follow the instructions at [Add Basic Authentication And TLS Using Apache](https://docs.bitnami.com/google/apps/elasticsearch/administration/add-basic-auth-and-tls/).


## PDF viewers

pdf.js

[CORE PDF Reader](https://github.com/oacore/reader)
