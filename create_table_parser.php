<?php
class create_table_parser
{
    public function parse($sql)
    {
        list($table, $columns_and_constraints_sql) = $this->parse_table($sql);
        list($table['constraints'], $columns_sql) = $this->parse_table_constraints($columns_and_constraints_sql);
        $table['columns'] = $this->parse_columns($columns_sql);
        return $table;

    }

    public function parse_column_constraints($constraints)
    {
        $constraints = trim($constraints, "\n\r ,");
        $parts = preg_split('~(PRIMARY\s+KEY|NOT\s+NULL|UNIQUE|CHECK|DEFAULT|COLLATE|REFERENCES)~iu', $constraints, null, PREG_SPLIT_DELIM_CAPTURE);
        $parts = $this->remove_first_part($parts);
        $constraints = $this->parse_constraints($parts);

        return $constraints;
    }

    public function parse_columns($columns_sql)
    {
        $parts = preg_split('~["`]?([\w]+)["`]?\s+(TEXT|NUMERIC|INTEGER|REAL|BLOB)~iu', $columns_sql, null, PREG_SPLIT_DELIM_CAPTURE);
        $parts = $this->remove_first_part($parts);
        $parsed_columns  = array_chunk($parts, 3);
        $columns = [];

        foreach ($parsed_columns as $column) {
            list($name, $type, $constraints) = $column;

            $columns[] = [
                'name'       => $name,
                'type'       => $type,
                'constraints' => $this->parse_column_constraints($constraints),
            ];
        }

        return $columns;
    }

    public function parse_constraints($parts, $add_left_parenthesis = false)
    {
        $parsed_constraints  = array_chunk($parts, 2);
        $constraints = [];

        foreach ($parsed_constraints as $constraint) {
            list($name, $definition) = $constraint;
            $name = preg_replace('~\s+~', ' ', $name);
            $definition = trim($definition);

            if ($add_left_parenthesis) {
                $definition = '(' . $definition;
            }

            $constraints[$name][] = $definition;
        }

        return $constraints;
    }

    public function parse_table($sql)
    {
        if (! preg_match('~^\s*CREATE\s+TABLE\s+["`]?([\w]+)["`]?\s*\((.+?)\)(\s+WITHOUT ROWID)?\s*$~isu', $sql, $match)) {
            throw new Exception('Syntax error');
        }

        $table = [
            'name'           => $match[1],
            'columns'        => null,
            'constraints'    => null,
            'without_rowid'  => ! empty($match[3]),
        ];

        $columns_and_constraints_sql = $match[2];

        return [$table, $columns_and_constraints_sql];
    }

    public function parse_table_constraints($columns_and_constraints_sql)
    {
        $parts = preg_split('~,\s*(PRIMARY\s+KEY|UNIQUE|CHECK|FOREIGN\s+KEY)\s*\(~iu', $columns_and_constraints_sql, null, PREG_SPLIT_DELIM_CAPTURE);
        $columns_sql = array_shift($parts);
        $constraints = $this->parse_constraints($parts, true);

        return [$constraints, $columns_sql];
    }

    public function remove_first_part($parts)
    {
        $first_part = array_shift($parts);

        if (trim($first_part)) {
            throw new Exception("Syntax error near: $first_part");
        }

        return $parts;
    }
}

