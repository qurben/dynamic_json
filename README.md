# Dynamic JSON Datasource

- Version: 0.1.2
- Author: Gerben Oolbekkink
- Release date: 06-24-13
- Requirements: Symphony 2.3.2

## Description

With more and more API's switching to pure JSON —think of Twitter, Rottentomatoes, etc— sometimes there is no option but to use a JSON API. This extension allows you to create a JSON-based datasource just like an XML-based datasource; the JSON is converted to XML in the backend and provided to the frontend as pure XML.

## Installation

1. Place the `dynamic_json` folder in your Symphony `extensions` directory.
2. Go to _System > Extensions_, select "Dynamic JSON Datasource", choose "Enable" from the with-selected menu, then click Apply.

## Usage

1. Go to _Blueprints > Data sources_ and click _Create new_
2. Choose a name for your data source
3. From _Source_ select under __From extensions__ _Dynamic JSON_
4. Provide a URL to a valid JSON page
5. Use an xpath expression to select only a certain piece of the document
6. Click _Create Data Source_
7. Treat your newly created data source as any other _Dynamic XML_ data source

## Hints

To see the XML generated from this data source just add it to a page and use the Debug Devkit _(?debug)_ to inspect the XML.

## Roadmap

I am looking at implementing some kind of OAuth implementation to properly include Twitter on a website. See bd4dc9aecbb6edf2689dce3060dad491671583fb

## Notes

This extension is still in development, so use it at your own risk. It should work just as stable as _Dynamic XML_. Be aware of the fact that the XML structure may change after updating to a next version; it is now just made to work, tidying up is next.

## Changes

0.1.2: Generated JSON is much cleaner, the default class provided in Symphony creates a lot of junk
