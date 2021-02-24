<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

//l'anotation suivante permet de préceder toute les route de cette route par ce chemin
/**
 * Class PostController
 * @Route("/dashboard/post")
 */
class PostController extends AbstractController
{
    /**
     * @IsGranted("ROLE_JOURNALIST")
     * @Route("/create", name="post_create", methods={"GET|POST"})
     * ex: http://localhost:8000/dashboard/post/create
     * @param Request $request
     * @param SluggerInterface $slugger
     * @return Response
     */
    public function create(Request $request, SluggerInterface $slugger): Response
    {
        #Création d'un nouvel article VIDE
        $post = new Post();
        $post->setCreatedAt(new \DateTime());

        #Attribution d'un auteur à un article
        $post->setUser( $this->getUser() );

        #Création d'un formulaire
        $form =$this->createFormBuilder( $post )
            ->add('name',TextType::class, [
                'label'=> "Titre de l'article"
            ])
            ->add('category', EntityType::class, [
                'label'=> "Choisisez une catégorie",
                'class'=> Category::class,
                'choice_label'=> 'name',
            ])
            ->add('content', TextareaType::class, ['label'=> "Ecrivez votre article",])
            ->add('image',FileType::class, ['label'=> "Choisisez une image d'illustration",] )
            ->add( 'submit', SubmitType::class, [
                'label' => 'Publier votre article',
            ])
            ->getForm();

        #permet a symfony de gérer les données saisie par l'utilisateur
        $form->handleRequest( $request );

        if($form->isSubmitted() && $form->isValid()){

            # Upload de l'image
            /** @var UploadedFile $image */
            $image = $form->get('image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($image) {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                // Move the file to the directory where images are stored
                try {
                    $image->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    #notification d'erreur
                    $this->addFlash('danger', 'Une erreur est survenue durant le chargement de votre image.');

                }

                $post->setImage($newFilename);

            }//endIf image

            # Génération de l'alias
            $post->setAlias(
                $slugger->slug(
                    $post->getName()
                )
            );

            # Insertion dans la BDD
            $em = $this->getDoctrine()->getManager();
            $em->persist($post);
            $em->flush();

            # Notification de confirmation
            $this->addFlash('success', 'Félicitation, votre article est en ligne.');

            # Redirection vers le nouvel article
            return $this->redirectToRoute('default_post',[
                'category' => $post->getCategory()->getAlias(),
                'alias' => $post->getAlias(),
                'id' => $post->getId()
            ]);

        }

        #Passer le formulaire à la vue
        return $this->render('post/create.html.twig',[
            'form' =>$form->createView(),
        ]);
    }
}
