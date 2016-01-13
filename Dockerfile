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

# Temporary hack for php-memcache (before #5064 fix)
# See https://bugs.alpinelinux.org/issues/5064
# RUN apk add php-dev autoconf build-base zlib-dev && \
#     cd / && \
#     wget https://pecl.php.net/get/memcache-3.0.8.tgz && \
#     tar xvvzf memcache-3.0.8.tgz && \
#     cd memcache-3.0.8 && \
#     phpize && \
#     wget https://raw.githubusercontent.com/pld-linux/php-pecl-memcache/master/memcache-faulty-inline.diff && \
#     patch -p1 < memcache-faulty-inline.diff && \
#     ./configure --prefix=/usr && \
#     make && \
#     make install && \
#     echo "extension=memcache.so" > /etc/php/conf.d/memcache.ini && \
#     rm -rf /memcache-3.0.8 /memcache-3.0.8.tar.gz /package.xml && \
#     apk del php-dev autoconf build-base zlib-dev

# Temporary hack for php-memcache (with #5064 solved in edge)
# See https://bugs.alpinelinux.org/issues/5064
RUN sed -i -e 's/v3\.3/edge/g' /etc/apk/repositories && \
    apk update && \
    apk del php-memcache && \
    apk add php-memcache && \
    sed -i -e 's/edge/v3\.3/g' /etc/apk/repositories

RUN rm -rf /var/cache/apk/*

RUN npm install -g "less@<2.0.0"

RUN mkdir /atom
RUN git clone -b ${GIT_BRANCH} ${GIT_REPOSITORY} /atom/src
WORKDIR /atom/src

RUN cd ./plugins/arDominionPlugin && make
RUN cd ./plugins/arArchivesCanadaPlugin && make

ENTRYPOINT ["/atom/src/docker/entrypoint.sh"]
CMD ["fpm"]
