<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Skill> */
class SkillFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'PHP', 'JavaScript', 'TypeScript', 'Python', 'Java', 'C#', 'Go', 'Rust', 'Ruby',
            'Laravel', 'Vue.js', 'React', 'Angular', 'Symfony', 'Django', 'Spring Boot',
            'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'SQLite',
            'Git', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'GCP',
            'REST APIs', 'GraphQL', 'CI/CD', 'Agile', 'Scrum',
            'Communication', 'Problem Solving', 'Teamwork', 'Leadership',
        ]);

        return [
            'name' => $name,
            'normalized_name' => strtolower($name),
            'category' => fake()->randomElement(['language', 'framework', 'database', 'tool', 'cloud', 'methodology', 'soft-skill', null]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
