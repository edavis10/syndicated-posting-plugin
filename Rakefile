#!/usr/bin/env ruby
require "fileutils"

require 'rake/clean'
CLEAN.include('**/semantic.cache','./syndicated-posting.zip')

PLUGIN_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/earthzine/wp-content/plugins'
SRC_FOLDER = '/home/edavis/dev/Business/Customers/Shane-and-Peter/syndication-plugin/trunk'
ZIP_FILE = SRC_FOLDER + "/syndicated-posting.zip"

desc "Copy the plugin source to the plugin folder"
task :copy => [:remove] do
  cp_r("#{SRC_FOLDER}/syndicated-posting", "#{PLUGIN_FOLDER}")
end

desc "Remove the plugin source from the plugin folder"
task :remove do
  rm_rf("#{PLUGIN_FOLDER}/syndicated-posting")
end

desc "Zip of the folder for release"
task :zip => [:clean, :doc] do
  require 'zip/zip'
  require 'zip/zipfilesystem'
  
  # check to see if the file exists already, and if it does, delete it.
  if File.file?(ZIP_FILE)
    File.delete(ZIP_FILE)
  end 

  # open or create the zip file
  Zip::ZipFile.open(ZIP_FILE, Zip::ZipFile::CREATE) do |zipfile|
    # Should skip svn files
    files = Dir['syndicated-posting/**/*.*']

    files.each do |file|
      print "Adding #{file} ...."
      zipfile.add(file, file)
      puts ". done"
    end
  end
  
  # set read permissions on the file
  File.chmod(0644, ZIP_FILE)
end

desc "Upload zip file to server"
task :upload => [:zip] do
  system("scp -oPort=44444 #{ZIP_FILE} littlestreamsoftware.com:/home/websites/littlestreamsoftware/shared/uploaded-images/")
  puts "File is at http://www.littlestreamsoftware.com/images/assets/syndicated-posting.zip"
end

desc "Purge the wp_posts table for testing"
task :purge_db do 
  db = ENV['DB'] || 'wordpress'
  user = ENV['DBUSER'] || 'root'
  system("echo 'TRUNCATE TABLE wp_posts;' | mysql -u #{user} -p #{db}")
end

desc "Create the HTML docs from the txt"
task :doc do 
  system("markdown #{SRC_FOLDER}/syndicated-posting/README.txt > #{SRC_FOLDER}/syndicated-posting/README.html")
end


task :default => [:copy]
