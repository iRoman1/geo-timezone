#!/bin/bash

apt install php8.1-dev &&\
apt install php8.1-zip &&\
apt install php8.1-xml &&\
apt install php8.1-mbstring &&\

curl -s -O http://download.osgeo.org/geos/geos-3.9.3.tar.bz2 &&\
  tar -xjvf geos-3.9.3.tar.bz2 &&\
  cd geos-3.9.3/ &&\
  ./configure --enable-php &&\
  make &&\
  make install &&\
  cd .. &&\

ldconfig


git clone https://github.com/macellan/php-geos  &&\
  cd php-geos  &&\
  ./autogen.sh  &&\
  ./configure  &&\
  make  &&\
  make install

# Enable geos module in your system
# ls /etc/php &&\
# cat <<EOF > /etc/php/7.0/mods-available/geos.ini
# ; configuration for php geos module
# ; priority=50
# extension=geos.so
# EOF
#
# cd /etc/php/7.0/cli/conf.d &&\
# ln -s /etc/php/7.0/mods-available/geos.ini &&\
# phpenmod geos