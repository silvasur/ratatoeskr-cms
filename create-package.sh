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
wget https://raw.github.com/kch42/Stupid-Template-Engine/master/stupid_template_engine.php
wget http://michelf.com/docs/projets/php-markdown-1.0.1o.zip
unzip php-markdown-*.zip
mv PHP\ Markdown\ */markdown.php .
rm -rf PHP\ Markdown\ *
rm php-markdown-*.zip 
wget http://sourceforge.net/projects/kses/files/latest/download
unzip kses-*.zip
mv kses-*/kses.php .
rm -rf kses-*
rm kses-*.zip
wget http://code.jquery.com/jquery.min.js
cd ../..
rm session_doctor.php 
cd ..
zip -r Ratatoeskr.zip Ratatoeskr
