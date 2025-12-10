<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBookSimilaritiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('book_similarities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id_1');
            $table->unsignedBigInteger('book_id_2');
            $table->decimal('similarity_score', 5, 4);
            $table->timestamps();

            $table->foreign('book_id_1')->references('book_id')->on('books')->onDelete('cascade');
            $table->foreign('book_id_2')->references('book_id')->on('books')->onDelete('cascade');
            $table->index(['book_id_1', 'similarity_score']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('book_similarities');
    }
}
