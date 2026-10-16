<?php

namespace App\Services\CrmImport;

/** Sinaliza linha repetida dentro da própria planilha (vira "ignorado" no relatório, não erro). */
class RepetidoNoArquivo extends \DomainException {}
