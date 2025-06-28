# Purpose

This is the project for new Webstone website using Kubernetes.

# How to encode "hello" into BASE64

> echo -n "hello" | base64

# How to create Kubernetes Secret for using ghcr.io

namespace for secret should match with the namespace of pod

> kubectl -n chunkeng|webstone create secret docker-registry ghcr-io-registry
>   --docker-server=ghcr.io
>   --docker-username=Hohyun
>   --docker-password=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
>   --docker-email=skykim63@gmail.com
