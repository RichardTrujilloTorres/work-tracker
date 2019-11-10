<?php

namespace App\Repository;

use App\Entity\Commit;
use App\Entity\Entry;
use App\Exception\NotFoundException;
use App\Repository\Traits\Deletes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Entry|null find($id, $lockMode = null, $lockVersion = null)
 * @method Entry|null findOneBy(array $criteria, array $orderBy = null)
 * @method Entry[]    findAll()
 * @method Entry[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntryRepository extends ServiceEntityRepository
{
    use Deletes;

    /**
     * @var CommitRepository
     */
    private $commitsRepository;

    public function __construct(ManagerRegistry $registry, CommitRepository $commitRepository)
    {
        parent::__construct($registry, Entry::class);
        $this->commitsRepository = $commitRepository;
    }

    /**
     * @param array $data
     * @return Entry
     * @throws NotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function create(array $data): Entry
    {
        $entry = new Entry();
        $entry->setDescription($data['description']);
        $entry->setStartTime(new \DateTime(@$data['start_time']));
        $entry->setEndTime(new \DateTime(@$data['end_time']));


        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();

        // add commits if specified
        if (! empty(@$data['commits'])) {
            $shas = $this->getShasFromCommits($data['commits']);
            $this->associate($entry->getId(), $shas);
        }

        $this->getEntityManager()->flush();

        return $entry;
    }

    /**
     * @param array $commits
     * @return array
     */
    protected function getShasFromCommits(array $commits): array
    {
        $shas = [];
        foreach ($commits as $commit) {
            if (! empty(@$commit->sha)) {
                $shas[] = [
                    'sha' => $commit->sha,
                    'branch' => $commit->branch,
                    'repository' => $commit->repository,
                    'date' => $commit->date,
                ];
            }
        }

        return $shas;
    }

    /**
     * @param int $id
     * @throws NotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete(int $id)
    {
        $entry = $this->find($id);
        if (! $entry) {
            throw new NotFoundException('Could not find entry with ID '. $id);
        }

        $this->getEntityManager()->remove($entry);
        $this->getEntityManager()->flush();
    }

    /**
     * @param int $id
     * @param array $sha
     * @return Entry|null
     * @throws NotFoundException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function associate(int $id, array $sha)
    {
        $entry = $this->find($id);
        if (! $entry) {
            throw new NotFoundException('Could not find entry with ID '. $id);
        }

        $commits = $this->commitsRepository->findBySha($sha);
        $foundShas = [];
        /**
         * @var $commit Commit
         */
        foreach ($commits as $commit) {
            $foundShas[] = $commit->getSha();
        }

        // define commits to be created
        $toBeCreatedShas = [];
        foreach ($sha as $single) {
            if (! in_array($single['sha'], $foundShas)) {
                $toBeCreatedShas[] = $single;
            }
        }

        // create them
        $newCommits = [];
        foreach ($toBeCreatedShas as $toBeCreatedSha) {
            $newCommits[] = $newCommit = $this->commitsRepository->create([
                'repository' => @$toBeCreatedSha['repository'],
                'branch' => @$toBeCreatedSha['branch'],
                'date' => @$toBeCreatedSha['date'],
                'sha' => $toBeCreatedSha['sha'],
            ]);

            $this->getEntityManager()->persist($newCommit);
        }

        // associate the entry
        $commits = array_merge($commits, $newCommits);
        foreach ($commits as $commit) {
            $entry->addCommit($commit);
        }

        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();

        return $entry;
    }

    // /**
    //  * @return Entry[] Returns an array of Entry objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Entry
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
