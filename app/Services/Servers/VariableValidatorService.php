<?php
/**
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * This software is licensed under the terms of the MIT license.
 * https://opensource.org/licenses/MIT
 */

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Exceptions\DisplayValidationException;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface;
use Pterodactyl\Contracts\Repository\ServerVariableRepositoryInterface;

class VariableValidatorService
{
    /**
     * @var bool
     */
    protected $isAdmin = false;

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $results = [];

    /**
     * @var \Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface
     */
    protected $optionVariableRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\ServerRepositoryInterface
     */
    protected $serverRepository;

    /**
     * @var \Pterodactyl\Contracts\Repository\ServerVariableRepositoryInterface
     */
    protected $serverVariableRepository;

    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validator;

    /**
     * VariableValidatorService constructor.
     *
     * @param \Pterodactyl\Contracts\Repository\EggVariableRepositoryInterface    $optionVariableRepository
     * @param \Pterodactyl\Contracts\Repository\ServerRepositoryInterface         $serverRepository
     * @param \Pterodactyl\Contracts\Repository\ServerVariableRepositoryInterface $serverVariableRepository
     * @param \Illuminate\Contracts\Validation\Factory                            $validator
     */
    public function __construct(
        EggVariableRepositoryInterface $optionVariableRepository,
        ServerRepositoryInterface $serverRepository,
        ServerVariableRepositoryInterface $serverVariableRepository,
        ValidationFactory $validator
    ) {
        $this->optionVariableRepository = $optionVariableRepository;
        $this->serverRepository = $serverRepository;
        $this->serverVariableRepository = $serverVariableRepository;
        $this->validator = $validator;
    }

    /**
     * Set the fields with populated data to validate.
     *
     * @param array $fields
     * @return $this
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Set this function to be running at the administrative level.
     *
     * @param bool $bool
     * @return $this
     */
    public function isAdmin($bool = true)
    {
        $this->isAdmin = $bool;

        return $this;
    }

    /**
     * Validate all of the passed data aganist the given service option variables.
     *
     * @param int $option
     * @return $this
     */
    public function validate($option)
    {
        $variables = $this->optionVariableRepository->findWhere([['egg_id', '=', $option]]);
        if (count($variables) === 0) {
            $this->results = [];

            return $this;
        }

        $variables->each(function ($item) {
            // Skip doing anything if user is not an admin and variable is not user viewable
            // or editable.
            if (! $this->isAdmin && (! $item->user_editable || ! $item->user_viewable)) {
                return;
            }

            $validator = $this->validator->make([
                'variable_value' => array_key_exists($item->env_variable, $this->fields) ? $this->fields[$item->env_variable] : null,
            ], [
                'variable_value' => $item->rules,
            ]);

            if ($validator->fails()) {
                throw new DisplayValidationException(json_encode(
                    collect([
                        'notice' => [
                            trans('admin/server.exceptions.bad_variable', ['name' => $item->name]),
                        ],
                    ])->merge($validator->errors()->toArray())
                ));
            }

            $this->results[] = [
                'id' => $item->id,
                'key' => $item->env_variable,
                'value' => $this->fields[$item->env_variable],
            ];
        });

        return $this;
    }

    /**
     * Return the final results after everything has been validated.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
