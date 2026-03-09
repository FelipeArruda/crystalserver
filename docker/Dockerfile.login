FROM golang:1.24-alpine AS build

RUN apk add --no-cache git
RUN git clone --depth 1 https://github.com/opentibiabr/login-server.git /src

WORKDIR /src
COPY docker/login-patches/api.go /src/src/api/api.go
COPY docker/login-patches/login.go /src/src/api/login.go
RUN CGO_ENABLED=0 go build -o /out/login-server ./src

FROM alpine:3.21
RUN apk add --no-cache ca-certificates tzdata

COPY --from=build /out/login-server /bin/login-server
COPY config.lua.dist /config.lua
COPY data/XML/events.xml /data/XML/events.xml

EXPOSE 80 9090
ENTRYPOINT ["/bin/login-server"]
