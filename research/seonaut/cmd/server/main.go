package main

import (
	"flag"
	_ "time/tzdata" // embed tz database so TZ env var works in Alpine without tzdata package

	"github.com/stjudewashere/seonaut/internal/routes"
	"github.com/stjudewashere/seonaut/internal/services"
)

func main() {
	var configFile string

	flag.StringVar(&configFile, "c", "config", "Specify configuration file. Default is config.")
	flag.Parse()

	container := services.NewContainer(configFile)
	routes.NewServer(container)
}
