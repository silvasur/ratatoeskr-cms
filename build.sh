#!/bin/sh

set -e

mkdir_if_missing() {
    test -d "$1" || mkdir -p "$1"
}

setup_directories() {
    mkdir_if_missing images/previews
    mkdir_if_missing ratatoeskr/plugin_extradata/public
    mkdir_if_missing ratatoeskr/templates/src/plugintemplates
    mkdir_if_missing ratatoeskr/templates/transc
}

install_dependencies() {
    composer install

    cd ratatoeskr/libs

    wget -O kses.zip http://sourceforge.net/projects/kses/files/kses/0.2.2/kses-0.2.2.zip/download?use_mirror=optimate
    unzip kses.zip
    mv kses-*/kses.php .
    rm -rf kses-*
    rm kses.zip

    wget http://code.jquery.com/jquery.min.js

    cd ../..
}

setup_dev_environment() {
    setup_directories
    install_dependencies
}

create_release_package() {
    git archive --format=tar -o r7r.tar master
    mkdir -p package/Ratatoeskr
    mv r7r.tar package/Ratatoeskr

    cd package/Ratatoeskr
    tar xf r7r.tar
    rm r7r.tar

    setup_directories
    install_dependencies

    cd ..

    zip -r Ratatoeskr.zip Ratatoeskr
}

case "$1" in
setup_directories)
    setup_directories
    ;;
install_dependencies)
    install_dependencies
    ;;
setup_dev_environment)
    setup_dev_environment
    ;;
create_release_package)
    create_release_package
    ;;
*)
    {
        echo "usage: $0 mode"
        echo ""
        echo "where mode is one of:"
        echo "- setup_directories"
        echo "- install_dependencies"
        echo "- setup_dev_environment"
        echo "- create_release_package"
    } >&2
    exit 1
    ;;
esac
