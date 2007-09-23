#!/usr/bin/env ruby
require "fileutils"

require 'rake/clean'
CLEAN.include('**/semantic.cache','./syndicated-posting.zip')

PLUGIN_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/earthzine/wp-content/plugins'
SRC_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/syndication-plugin/trunk'

desc "Copy the plugin source to the plugin folder"
task :copy => [:remove] do
  cp_r("#{SRC_FOLDER}/syndicated-posting", "#{PLUGIN_FOLDER}")
end

desc "Remove the plugin source from the plugin folder"
task :remove do
  rm_rf("#{PLUGIN_FOLDER}/syndicated-posting")
end

desc "Zip of the folder for release"
task :zip => [:clean] do
  require 'zip/zip'
  require 'zip/zipfilesystem'
  
  bundle_filename = "./syndicated-posting.zip"

  # check to see if the file exists already, and if it does, delete it.
  if File.file?(bundle_filename)
    File.delete(bundle_filename)
  end 

  # open or create the zip file
  Zip::ZipFile.open(bundle_filename, Zip::ZipFile::CREATE) do |zipfile|
    # Should skip svn files
    files = Dir['syndicated-posting/**/*.*']

    files.each do |file|
      print "Adding #{file} ...."
      zipfile.add(file, file)
      puts ". done"
    end
  end
  
  # set read permissions on the file
  File.chmod(0644, bundle_filename)
end

task :default => [:copy]
