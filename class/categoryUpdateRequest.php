<?php

class CategoryUpdateRequest {
  // xmlで自動生成するときに順番通りに要素が並ぶ
  // RMS APIは順番を組み替えると400が返ってくるので注意すること
  // public $categorySetManageNumber; //※ TODOメガショッププランだと必要なはずだがAPI仕様上使えないことになっている。どうする
  public $categoryId; // どのカテゴリーを更新するか
  public $category; // Categoryクラスオブジェクトが入る
  
  function __construct() {

  }
}