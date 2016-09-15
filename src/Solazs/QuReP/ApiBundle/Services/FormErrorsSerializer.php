<?php

namespace Solazs\QuReP\ApiBundle\Services;


use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;


/**
 * Class FormErrorsSerializer
 *
 * Custom serializer to render property error messages when a form validation fails.
 *
 * Credit for Graceas: https://gist.github.com/Graceas/6505663
 *
 * @package Solazs\QuReP\ApiBundle\Services
 */
class FormErrorsSerializer
{

    public function serializeFormErrors(
      FormInterface $form,
      $flat_array = false,
      $add_form_name = false,
      $glue_keys = '_'
    ) {
        $errors = array();
        $errors['global'] = array();
        $errors['fields'] = array();

        foreach ($form->getErrors() as $error) {
            $errors['global'][] = $error->getMessage();
        }

        $errors['fields'] = $this->serialize($form);

        if ($flat_array) {
            $errors['fields'] = $this->arrayFlatten(
              $errors['fields'],
              $glue_keys,
              (($add_form_name) ? $form->getName() : '')
            );
        }


        return $errors;
    }

    private function serialize(FormInterface $form)
    {
        $local_errors = array();
        foreach ($form as $key => $child) {

            /** @var FormError $error */
            foreach ($child->getErrors() as $error) {
                $local_errors[$key] = $error->getMessage();
            }

            if (count($child) > 0) {
                $local_errors[$key] = $this->serialize($child);
            }
        }

        return $local_errors;
    }

    private function arrayFlatten($array, $separator = "_", $flattened_key = '')
    {
        $flattenedArray = array();
        foreach ($array as $key => $value) {

            if (is_array($value)) {

                $flattenedArray = array_merge(
                  $flattenedArray,
                  $this->arrayFlatten(
                    $value,
                    $separator,
                    (strlen($flattened_key) > 0 ? $flattened_key.$separator : "").$key
                  )
                );

            } else {
                $flattenedArray[(strlen($flattened_key) > 0 ? $flattened_key.$separator : "").$key] = $value;
            }
        }

        return $flattenedArray;
    }

}