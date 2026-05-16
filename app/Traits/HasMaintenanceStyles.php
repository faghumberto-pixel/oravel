<?php

   namespace App\Traits;

   trait HasMaintenanceStyles
   {
       public static function getStatusColor(string $status): string
       {
           return match ($status) {
               'aguardando_diagnostico' => 'danger',  // Vermelho
               'em_manutencao'          => 'warning', // Laranja
               'aguardando_peca'        => 'info',    // Azul
               'concluido'              => 'success', // Verde
               default                  => 'gray',
           };
       }

       public static function getStatusLabel(string $status): string
       {
           return match ($status) {
               'aguardando_diagnostico' => 'Aguardando Diagnóstico',
               'em_manutencao'          => 'Em Manutenção',
               'aguardando_peca'        => 'Aguardando Peça',
               'concluido'              => 'Concluído',
               default                  => ucfirst(str_replace('_', ' ', $status)),
           };
       }
   }
