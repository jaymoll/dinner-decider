<?php

use Illuminate\Support\Facades\Route;

Route::livewire('ingredients', 'pages::ingredients.index')->name('ingredients.index');
Route::livewire('ingredients/create', 'pages::ingredients.create')->name('ingredients.create');
Route::livewire('ingredients/archive', 'pages::ingredients.archive')->name('ingredients.archive');
Route::livewire('ingredients/{ingredient}/edit', 'pages::ingredients.edit')->name('ingredients.edit');

Route::livewire('recipes', 'pages::recipes.index')->name('recipes.index');
Route::livewire('recipes/create', 'pages::recipes.create')->name('recipes.create');
Route::livewire('recipes/archive', 'pages::recipes.archive')->name('recipes.archive');
Route::livewire('recipes/{recipe}/edit', 'pages::recipes.edit')->name('recipes.edit');
Route::livewire('recipes/{recipe}', 'pages::recipes.show')->name('recipes.show');
