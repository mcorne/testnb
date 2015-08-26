<?php
class create_table_parser
{
    public $table;
    public $string;

    public function __toString()
    {
        if (! $this->table) {
            return '';
        }

        if (! $this->string) {
            $this->string = $this->convert_table_to_string($this->table);
        }

        return $this->string;
    }

    public function convert_column_to_string($column)
    {
        $string = "{$column['name']} {$column['type']}";

        if (isset($column['constraints'])) {
            $string .= ' ' . $this->convert_constraints_to_string($column['constraints']);
        }

        return $string;
    }

    public function convert_columns_to_string($columns)
    {
        $strings = array_map([$this, 'convert_column_to_string'], $columns);
        $string = implode("\n", $strings);

        return $string;
    }

    public function convert_constraints_to_string($constraints, $separator = ' ')
    {
        $string = '';

        foreach ($constraints as $name => $definitions) {
            foreach ($definitions as $definition) {
                if ($string) {
                    $string .= $separator;
                }
                
                $string .= $name;

                if ($definition) {
                    $string .= ' ' . $definition;
                }
            }
        }

        return $string;
    }

    public function convert_table_to_string($table)
    {
        $definition = $this->convert_columns_to_string($table['columns']);

        if (isset($table['constraints'])) {
            $definition .= "\n" . $this->convert_constraints_to_string($table['constraints'], ",\n");
        }

        $string = sprintf("CREATE TABLE %s(\n%s\n)", $table['name'], $definition);

        if (isset($table['without_rowid'])) {
            $string .= ' WITHOUT ROWID';
        }

        return $string;
    }

    public function parse($sql)
    {
        list($table, $columns_and_constraints_sql) = $this->parse_table($sql);
        list($table['constraints'], $columns_sql) = $this->parse_table_constraints($columns_and_constraints_sql);
        $table['columns'] = $this->parse_columns($columns_sql);

        $this->table = $table;
        $this->string = null;

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
        if (! preg_match('~^\s*CREATE\s+TABLE\s+["`]?([\w]+)["`]?\s*\((.+?)\)(\s+WITHOUT\s+ROWID)?\s*$~isu', $sql, $match)) {
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

