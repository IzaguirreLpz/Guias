<?php

namespace Modules\Andina\Http\Controllers\Api;

use App\Abstracts\Http\ApiController; // * 
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Dingo\Api\Routing\Helpers;

use App\Jobs\Document\CreateDocument; // * Importamos el job para generar facturas
use App\Traits\Documents; // * Importamos los metodos para generar el siguiente nuemero de factura
use App\Transformers\Document\Document as Transformer; // * Importamos la libreria que nos permite devolver el resultado despues de crear la factura
use Date; // * Libreria que nos sirve para enviar el ping


class Factura extends ApiController
{
    use Helpers, Documents; // * Le indicamos a nuestra clase llamada Ping que puede acceder a los metodos de Documents

    /**
     * Instantiate a new controller instance.
     */
    public function __construct()
    {
        // do nothing but override permissions
    }

    //* Funcion para determinar si la conexion es exitosa
    public function ping()
    {
        return $this->response->array([
            'status' => 'ok',
            'timestamp' => Date::now(),
        ]);
    }

    /*
    * Custom Code Juan Carlos Izaguirre
    ? El objetivo principal de este codigo es el de crear una factura completa en nuestro sistema 
    ? de Akaunting, para este Api en especifico y por buenas practicas nuestras facturas quedaran
    ? como borrador. 
    */

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // * Utilizando nuestra clase de Document generamos el numero de Factura Automaticamente.
        $document_number =  $this->getNextDocumentNumber('invoice');
        /* 
         Lo primero que se tiene que hacer es crear la factura, nuestra Api debera exigir los campos de
         [description y comision] con esta informacion podremos proceder
         a crear la factura para ello agregamos algunos campos a nuestro arreglo del request
        */
        // * En esta primera etapa creamos la lista de detalles que tendra nuestra factura, como ser cliente, fecha etc.
        $addRequest = [
            'company_id' => 1, 'type' => 'invoice', 'status' => 'draft',
            'issued_at' => now(), 'due_at' => now(), 'currency_code' => 'HNL', 'currency_rate' => 1.00000000,
            'category_id' => 3, 'contact_id' => 1, 'contact_name' => 'Nombre Del Cliente',
            'contact_tax_number' => '999999999999999', 'parent_id' => 0, 'created_by' => 1, 'created_at' => now(),
            'updated_at' => now(), 'document_number' => $document_number
        ];

        // * Este segundo paso nos sirve para agregar objetos a nuestra factura, detalle de producto/servicio
        $items = array(
            'items' =>
            array(
                'data' =>
                array(
                    /*  
                     ! El item id debe ser el mismo al que se este usando dentro del sistema de
                     ! Akaunting el cual debe ser previamente creado, el precio y descripcion dentro del sistema.
                     ! son irrelevantes ya que se pueden cambiar al momento de facturar.
                     ! Debemos asegurarnos de crear el item de Comisiones antes para no crear multiples productos. 
                     */
                    'item_id' => '47',
                    'name' => 'Product Name',
                    'description' => $request['description'],
                    'quantity' => 1,
                    'price' => $request['comision'],
                    'total' => $request['comision'],
                    // ! El tax id debe ser el mismo al que se este usando dentro del sistema de
                    // ! Akaunting el cual debe ser previamente configurado.
                    'tax_ids' => 2,
                ),
            ),
            // * Una vez agregado el item le asignamos el impuesto que quenecesitamos.
            'item_taxes' =>
            array(
                'data' =>
                array(
                    // ! El tax id debe ser el mismo al que se este usando dentro del sistema de
                    // ! Akaunting el cual debe ser previamente configurado.
                    'tax_id' => 2,
                    'name' => 'ISV',
                ),
            )
        );

        // ? Con nuestras listas finalizadas podemos proceder a unificar nuestras listas con el arreglo del request
        $request = $request->merge($addRequest);
        $request = $request->merge($items);
        // ! Ejecutamos el Job que tiene predefinido Akaunting para generar Facturas.
        $document = $this->dispatch(new CreateDocument($request));
        // ? Devolvemos el resultado de nuestra factura creada.
        return $this->response->created(route('api.documents.show', $document->id), $this->item($document, new Transformer()));
    }
}
