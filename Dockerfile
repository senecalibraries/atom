FROM alpine:latest

ENV GIT_REPOSITORY https://github.com/artefactual/atom.git
ENV GIT_BRANCH dev/docker

RUN apk update && \
    apk upgrade && \
    apk add -v \
        php \
        php-ctype \
        php-curl \
        php-gettext \
        php-iconv \
        php-json \
        php-fpm \
        php-memcache \
        php-mcrypt \
        php-mysql \
        php-mysqli \
        php-opcache \
        php-pdo \
        php-pdo_mysql \
        php-sockets \
        php-xml \
        php-xmlreader \
        php-xsl \
        php-zip \
        ca-certificates \
        git \
        ffmpeg \
        imagemagick \
        ghostscript \
        poppler-utils \
        nodejs \
        make \
        bash

RUN rm -rf /var/cache/apk/*

RUN npm install -g "less@<2.0.0"

RUN mkdir /atom
RUN git clone -b ${GIT_BRANCH} ${GIT_REPOSITORY} /atom/src
WORKDIR /atom/src

RUN cd ./plugins/arDominionPlugin && make
RUN cd ./plugins/arArchivesCanadaPlugin && make

ENTRYPOINT ["/atom/src/docker/entrypoint.sh"]
CMD ["fpm"]
