#!/usr/bin/env bash

git archive --format=tar -o r7r.tar master
mkdir -p package/Ratatoeskr
mv r7r.tar package/Ratatoeskr
cd package/Ratatoeskr
tar xf r7r.tar
rm r7r.tar
mkdir -p images/previews
cd ratatoeskr/
mkdir plugin_extradata/public
mkdir templates/src/plugintemplates
mkdir templates/transc
cd libs
test -d ste || mkdir ste
cd ste
wget https://github.com/silvasur/ste/archive/master.zip
unzip master.zip
cp ste-master/ste.php .
cp -r ste-master/src .
rm -rf ste-master master.zip
cd ..
wget http://michelf.com/docs/projets/php-markdown-1.0.1o.zip
unzip php-markdown-*.zip
mv PHP\ Markdown\ */markdown.php .
rm -rf PHP\ Markdown\ *
rm php-markdown-*.zip 
wget -O kses.zip http://sourceforge.net/projects/kses/files/kses/0.2.2/kses-0.2.2.zip/download?use_mirror=optimate
unzip kses.zip
mv kses-*/kses.php .
rm -rf kses-*
rm kses.zip
wget http://code.jquery.com/jquery.min.js
cd ../..
rm session_doctor.php 
cd ..
zip -r Ratatoeskr.zip Ratatoeskr
