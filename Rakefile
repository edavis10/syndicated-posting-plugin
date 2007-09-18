#!/usr/bin/env ruby
require "fileutils"

PLUGIN_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/earthzine/wp-content/plugins'
SRC_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/syndication-plugin/trunk'

desc "Copy the plugin source to the plugin folder"
task :copy do
  cp_r("#{SRC_FOLDER}/syndicated-posting", "#{PLUGIN_FOLDER}")
end

desc "Remove the plugin source from the plugin folder"
task :remove do
  rm_rf("#{PLUGIN_FOLDER}/syndicated-posting")
end

