<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('general_expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->date('expense_date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('general_expenses');
    }
};
