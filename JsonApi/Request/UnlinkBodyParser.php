<?php
/**
 * @copyright 2014 Integ S.A.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 * @author Javier Lorenzana <javier.lorenzana@gointegro.com>
 */

namespace GoIntegro\Hateoas\JsonApi\Request;

// HTTP.
use Symfony\Component\HttpFoundation\Request;
// JSON.
use GoIntegro\Hateoas\Util;
// Collections.
use Doctrine\Common\Collections\Collection;

/**
 * @see http://jsonapi.org/format/#crud-updating
 */
class UnlinkBodyParser
{
    const LINKS = 'links';

    const ERROR_EMPTY_BODY = "The resource data was not found on the body.",
        ERROR_RELATIONSHIP_TYPE = "The type of the relationship Ids is unexpected",
        ERROR_RELATIONSHIP_EXISTS = "The relationships \"%s\" already exist.",
        ERROR_RELATIONSHIPS_NOT_FOUND = "The relationships \"%s\" were not found.",
        ERROR_RELATIONSHIP_NOT_FOUND = "The relationship was not found.";

    /**
     * @param Request $request
     * @param Params $params
     * @return array
     */
    public function parse(Request $request, Params $params)
    {
        $entity = $params->entities->primary->first();
        $ids = NULL;

        // @todo Encapsulate.
        $method = 'get' . Util\Inflector::camelize($params->relationship);
        $relation = $entity->$method();

        if ($relation instanceof Collection) {
            $relation = $relation->toArray();
        }

        $ids = $this->parseDeleteAction($params, $relation);

        $entityData = [
            (string) $entity->getId() => [
                self::LINKS => [
                    $params->relationship => $ids
                ]
            ]
        ];

        return $entityData;
    }

    /**
     * @param Params $params
     * @param mixed $relation
     * @return mixed
     * @throws ParseException
     * @throws RelationshipNotFoundException
     */
    protected function parseDeleteAction(Params $params, $relation)
    {
        $ids = NULL;

        if (!empty($params->relationshipIds)) {
            if (!is_array($relation)) {
                throw new ParseException(
                    self::ERROR_RELATIONSHIP_TYPE
                );
            }

            $callback = function($entity) {
                return (string) $entity->getId();
            };
            $current = array_map($callback, $relation);
            $targets = array_map($callback, $params->entities->relationship);
            $diff = array_diff($targets, $current);

            if (!empty($diff)) {
                $message = sprintf(
                    self::ERROR_RELATIONSHIPS_NOT_FOUND,
                    implode('", "', $diff)
                );
                throw new RelationshipNotFoundException($message);
            }

            $ids = array_diff($current, $targets);
        } elseif (is_array($relation)) {
            $ids = [];
        } elseif (empty($relation)) {
            throw new RelationshipNotFoundException(
                self::ERROR_RELATIONSHIP_NOT_FOUND
            );
        }

        return $ids;
    }
}
